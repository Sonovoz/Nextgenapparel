<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Facturacion\EmitirFactura;
use App\Dominio\Incidencias\IncidenciaNoValida;
use App\Dominio\Incidencias\ResolucionIncidencia;
use App\Dominio\Incidencias\ResolverIncidencia;
use App\Dominio\Pagos\DevolverDinero;
use App\Dominio\Pagos\Pasarela;
use App\Dominio\Pedidos\CambiarEstado;
use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Sistema\Acceso\Rol;
use App\Http\Controllers\PedidoController;
use App\Jobs\DevolverPedido;
use App\Models\Ajuste;
use App\Models\Devolucion;
use App\Models\Incidencia;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Devoluciones automáticas por Redsys (paso 7.2, D182): al cancelar un pedido cobrado y en las incidencias. */
class DevolucionesTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'; // pública, del entorno de pruebas de Redsys

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config()->set('pagos.pasarela', 'redsys');
        config()->set('pagos.redsys', ['comercio' => '999008881', 'terminal' => '1', 'clave' => self::CLAVE, 'entorno' => 'test', 'tarjeta' => true, 'bizum' => false, 'nombre' => 'Nextgenapparel']);
        app()->forgetInstance(Pasarela::class);
        Ajuste::guardar('facturacion.emisor', ['razon_social' => 'DTI Textil SL', 'nif' => 'B12345674', 'direccion' => 'Calle Industria 4', 'codigo_postal' => '28001', 'localidad' => 'Madrid']);

        $this->pedido = Pedido::factory()->create(['estado' => E::Pagado, 'numero' => 'PED-0042', 'total' => 13298, 'impuestos' => 2308, 'correo_confirmado_en' => now(),
            'desglose' => ['pasos' => [[], [], [], [], [], ['paso' => 'impuestos', 'detalle' => [['tipo_iva' => 2100, 'recargo' => 0, 'base' => 10990, 'cuota' => 2308, 'cuota_recargo' => 0]]]]]]);
        $this->pedido->lineas()->create(['tipo' => 'prenda', 'nombre' => ['es' => 'Camiseta'], 'unidades' => 15, 'prendas' => 6000, 'impresion' => 4500, 'descuento' => 0, 'cupon' => 0, 'neto' => 10500, 'tipo_iva' => 2100, 'recargo' => 0]);
        app(EmitirFactura::class)->delPedido($this->pedido);
        Pago::create(['pedido_id' => $this->pedido->id, 'pasarela' => 'redsys', 'metodo' => 'tarjeta', 'referencia' => '004212345678', 'importe' => 13298, 'estado' => 'cobrado', 'cobrado_en' => now()]);
    }

    /** La API REST de Redsys contesta firmado con el código dado (0900 = devolución hecha). */
    private string $codigo = '0900';

    private bool $falso = false;

    private function redsysContesta(string $codigo): void
    {
        $this->codigo = $codigo; // Http::fake se acumula: la respuesta sale de aquí
        if ($this->falso) {
            return;
        }
        $this->falso = true;
        Http::fake(['sis-t.redsys.es*' => function (PeticionHttp $peticion) {
            $codigo = $this->codigo;
            $enviado = json_decode((string) base64_decode($peticion['Ds_MerchantParameters']), true);
            $p = ['Ds_Order' => $enviado['DS_MERCHANT_ORDER'], 'Ds_Response' => $codigo, 'Ds_Amount' => $enviado['DS_MERCHANT_AMOUNT'], 'Ds_AuthorisationCode' => 'A1'];
            $codificado = strtr(base64_encode((string) json_encode($p)), '+/', '-_');
            $clavePedido = substr((string) openssl_encrypt(str_pad($p['Ds_Order'], 16, "\0"), 'des-ede3-cbc', (string) base64_decode(self::CLAVE), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 8)), 0, 16);

            return Http::response(['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => $codificado,
                'Ds_Signature' => strtr(base64_encode(hash_hmac('sha256', $codificado, $clavePedido, true)), '+/', '-_')]);
        }]);
    }

    public function test_cancelar_un_pedido_cobrado_devuelve_el_total_por_redsys(): void
    {
        $this->redsysContesta('0900');

        app(CambiarEstado::class)($this->pedido, E::Cancelado, $this->usuarioConRol(Rol::GestorTienda));

        $devolucion = Devolucion::sole();
        $this->assertSame([13298, 'hecha', '004212345678:A1'], [$devolucion->importe, $devolucion->estado, $devolucion->referencia]);
        Http::assertSent(fn (PeticionHttp $p) => json_decode((string) base64_decode($p['Ds_MerchantParameters']), true)['DS_MERCHANT_TRANSACTIONTYPE'] === '3');
        $this->assertSame(0, DevolverDinero::pendiente($this->pedido));
        $this->assertTrue($this->pedido->facturas()->get()->last()->tipo->esRectificativa());
    }

    public function test_si_redsys_no_devuelve_queda_nota_para_hacerlo_a_mano_y_no_repite_lo_hecho(): void
    {
        $this->redsysContesta('0190');
        $trabajo = new DevolverPedido($this->pedido->id);
        try {
            $trabajo->handle(app(DevolverDinero::class));
            $this->fail('Sin devolución, el trabajo se reintenta.');
        } catch (\RuntimeException $e) {
            $trabajo->failed($e);
        }
        $this->assertSame(['fallida', 1], [Devolucion::sole()->estado, Devolucion::sole()->intentos]);
        $this->assertStringContainsString('devuelve el dinero a mano', $this->pedido->notas()->sole()->texto);

        // El reintento con éxito usa otra clave ante Redsys y no devuelve dos veces.
        $this->redsysContesta('0900');
        $trabajo->handle(app(DevolverDinero::class));
        $trabajo->handle(app(DevolverDinero::class));
        $this->assertSame(['hecha', 13298], [Devolucion::sole()->estado, Devolucion::sole()->importe]);
        Http::assertSentCount(2);
    }

    public function test_el_reembolso_de_una_incidencia_va_por_redsys_y_si_falla_no_se_resuelve(): void
    {
        $this->pedido->update(['estado' => E::Entregado]);
        $incidencia = Incidencia::create(['uuid' => (string) Str::uuid(), 'codigo' => 'INC-0001', 'pedido_id' => $this->pedido->id, 'motivo' => 'impresion', 'descripcion' => 'Logo torcido en dos camisetas.', 'estado' => 'abierta']);
        $quien = $this->usuarioConRol(Rol::GestorTienda);

        $this->redsysContesta('0190');
        try {
            app(ResolverIncidencia::class)($incidencia, ResolucionIncidencia::Reembolso, 'Te devolvemos 20 €.', 2000, $quien);
            $this->fail('Sin devolución no se resuelve.');
        } catch (IncidenciaNoValida $e) {
            $this->assertStringContainsString('No se ha podido devolver', $e->motivo);
        }
        $this->assertSame(['abierta', 1], [$incidencia->fresh()->estado, $this->pedido->facturas()->count()]); // ni resuelta ni rectificativa

        $this->redsysContesta('0900');
        app(ResolverIncidencia::class)($incidencia, ResolucionIncidencia::Reembolso, 'Te devolvemos 20 €.', 2000, $quien);
        $this->assertSame(['resuelta', 2], [$incidencia->fresh()->estado, $this->pedido->facturas()->count()]);
        $this->assertSame([2000, 'hecha'], [Devolucion::where('estado', 'hecha')->sole()->importe, Devolucion::where('clave', 'incidencia-'.$incidencia->id)->sole()->estado]);
        $this->assertSame(11298, DevolverDinero::pendiente($this->pedido));
    }

    public function test_con_la_cancelacion_automatica_el_cliente_cancela_un_pedido_pagado_al_momento(): void
    {
        $this->redsysContesta('0900');
        Ajuste::guardar('pagos.cancelacion_automatica', true);

        $this->withSession([PedidoController::SESION => [$this->pedido->uuid]])->post(PedidoController::url('cancelar', $this->pedido))
            ->assertSessionHas('estado_pedido', __('pedidos.cancelar_cancelado_devolucion'));

        $this->assertSame(E::Cancelado, $this->pedido->fresh()->estado);
        $this->assertSame(13298, Devolucion::sole()->importe);
    }

    public function test_un_pedido_cobrado_fuera_de_la_pasarela_no_intenta_devolver(): void
    {
        Http::fake();
        Pago::query()->delete();

        app(CambiarEstado::class)($this->pedido, E::Cancelado);

        $this->assertSame(0, Devolucion::count());
        Http::assertNothingSent();
    }
}
