<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Pagos\Pasarela;
use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\AjustesPagos;
use App\Http\Controllers\PedidoController;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Pagar el pedido con Redsys (fase 7, D181), portado de Clave de Alma. */
class PagosTest extends TestCase
{
    use RefreshDatabase;

    // Datos públicos del entorno de pruebas de Redsys (no son de la tienda).
    private const CLAVE = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config()->set('pagos.pasarela', 'redsys');
        config()->set('pagos.redsys', ['comercio' => '999008881', 'terminal' => '1', 'clave' => self::CLAVE, 'entorno' => 'test', 'tarjeta' => true, 'bizum' => true, 'nombre' => 'Nextgenapparel']);
        app()->forgetInstance(Pasarela::class);
        $this->pedido = Pedido::factory()->create(['estado' => E::PendientePago, 'numero' => 'PED-0042', 'total' => 13298, 'correo_confirmado_en' => now()]);
    }

    /** Respuesta firmada como la manda Redsys (Base64 «URL-safe»), calculada aquí con openssl, sin FirmaRedsys. */
    private static function respuesta(array $p, string $clave = self::CLAVE): array
    {
        $codificado = strtr(base64_encode((string) json_encode($p)), '+/', '-_');
        $largo = (int) ceil(strlen($p['Ds_Order']) / 8) * 8;
        $clavePedido = substr((string) openssl_encrypt(str_pad($p['Ds_Order'], $largo, "\0"), 'des-ede3-cbc', (string) base64_decode($clave), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 8)), 0, $largo);

        return ['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => $codificado,
            'Ds_Signature' => strtr(base64_encode(hash_hmac('sha256', $codificado, $clavePedido, true)), '+/', '-_')];
    }

    private function pagar(string $metodo = 'tarjeta'): array
    {
        $html = $this->withSession([PedidoController::SESION => [$this->pedido->uuid]])
            ->post(route('pedidos.es.pagar', ['pedido' => $this->pedido->uuid]), ['metodo' => $metodo])
            ->assertOk()->assertSee('https://sis-t.redsys.es:25443/sis/realizarPago', false)->getContent();
        preg_match('/name="Ds_MerchantParameters" value="([^"]+)"/', $html, $m);

        return json_decode((string) base64_decode($m[1]), true);
    }

    public function test_pagar_lleva_a_redsys_con_el_total_y_el_metodo(): void
    {
        $p = $this->pagar('bizum');

        $this->assertSame(['13298', '978', '0', 'z', '001'], [$p['DS_MERCHANT_AMOUNT'], $p['DS_MERCHANT_CURRENCY'], $p['DS_MERCHANT_TRANSACTIONTYPE'], $p['DS_MERCHANT_PAYMETHODS'], $p['DS_MERCHANT_CONSUMERLANGUAGE']]);
        $this->assertMatchesRegularExpression('/^\d{12}$/', $p['DS_MERCHANT_ORDER']);
        $this->assertSame(route('pagos.aviso'), $p['DS_MERCHANT_MERCHANTURL']);
        $this->assertSame(['pendiente', 'bizum', 13298, true], [Pago::sole()->estado, Pago::sole()->metodo, Pago::sole()->importe, Pago::sole()->pruebas]);
    }

    public function test_el_aviso_firmado_da_el_pedido_por_pagado_una_sola_vez(): void
    {
        $orden = $this->pagar()['DS_MERCHANT_ORDER'];
        $aviso = self::respuesta(['Ds_Order' => $orden, 'Ds_Response' => '0000', 'Ds_Amount' => '13298', 'Ds_Currency' => '978']);

        $this->post(route('pagos.aviso'), $aviso)->assertOk();
        $this->post(route('pagos.aviso'), $aviso)->assertOk(); // Redsys repite avisos

        $this->assertSame(E::Pagado, $this->pedido->fresh()->estado);
        $this->assertSame(['cobrado', 1], [Pago::sole()->estado, $this->pedido->historial()->where('nota', 'like', 'Cobrado con Redsys (tarjeta)%PRUEBA')->count()]);
    }

    public function test_la_vuelta_del_cliente_tambien_vale_si_el_aviso_no_llega(): void
    {
        $orden = $this->pagar()['DS_MERCHANT_ORDER'];

        $this->get(route('pedidos.es.pago-vuelta', ['pedido' => $this->pedido->uuid]).'?'.http_build_query(self::respuesta(['Ds_Order' => $orden, 'Ds_Response' => '0000', 'Ds_Amount' => '13298'])))
            ->assertRedirect(PedidoController::url('ver', $this->pedido))->assertSessionHas('estado_pedido', __('pagos.cobrado'));
        $this->assertSame(E::Pagado, $this->pedido->fresh()->estado);
    }

    public function test_un_pago_rechazado_lo_explica_y_deja_pagar_otra_vez(): void
    {
        $orden = $this->pagar()['DS_MERCHANT_ORDER'];

        $this->get(route('pedidos.es.pago-vuelta', ['pedido' => $this->pedido->uuid]).'?'.http_build_query(self::respuesta(['Ds_Order' => $orden, 'Ds_Response' => '0116'])))
            ->assertSessionHas('aviso_pago', __('pagos.error_sin_saldo'));
        $this->assertSame([E::PendientePago, 'rechazado'], [$this->pedido->fresh()->estado, Pago::sole()->estado]);
        $this->pagar(); // otro intento, otro número
        $this->assertSame(2, Pago::count());
    }

    public static function avisosNoValidos(): array
    {
        return [
            'firmado con otra clave' => [true],
            'sin firma' => [false],
        ];
    }

    #[DataProvider('avisosNoValidos')]
    public function test_un_aviso_que_no_firma_redsys_no_cambia_nada(bool $firmado): void
    {
        $orden = $this->pagar()['DS_MERCHANT_ORDER'];
        $aviso = self::respuesta(['Ds_Order' => $orden, 'Ds_Response' => '0000', 'Ds_Amount' => '13298'], base64_encode(str_repeat('x', 24)));
        if (! $firmado) {
            unset($aviso['Ds_Signature']);
        }

        $this->post(route('pagos.aviso'), $aviso)->assertStatus(400);
        $this->assertSame([E::PendientePago, 'pendiente'], [$this->pedido->fresh()->estado, Pago::sole()->estado]);
    }

    /** Otro importe o un pedido ya cancelado: el cobro queda anotado para devolverlo, sin dar el pedido por pagado. */
    public function test_un_cobro_que_no_cuadra_queda_para_revisar(): void
    {
        $orden = $this->pagar()['DS_MERCHANT_ORDER'];

        $this->post(route('pagos.aviso'), self::respuesta(['Ds_Order' => $orden, 'Ds_Response' => '0000', 'Ds_Amount' => '100']))->assertOk();

        $this->assertSame([E::PendientePago, 'cobrado'], [$this->pedido->fresh()->estado, Pago::sole()->estado]);
        $this->assertStringContainsString('revísalo y devuelve', $this->pedido->notas()->sole()->texto);
    }

    public function test_sin_el_correo_confirmado_o_ya_pagado_no_se_paga(): void
    {
        $this->pedido->update(['correo_confirmado_en' => null]);
        $this->withSession([PedidoController::SESION => [$this->pedido->uuid]])->get(PedidoController::url('ver', $this->pedido))->assertDontSee(__('pagos.pagar'));
        $this->withSession([PedidoController::SESION => [$this->pedido->uuid]])->post(route('pedidos.es.pagar', ['pedido' => $this->pedido->uuid]))
            ->assertSessionHas('aviso_pago', __('pagos.no_disponible'));
        $this->flushSession();
        $this->post(route('pedidos.es.pagar', ['pedido' => $this->pedido->uuid]))->assertNotFound(); // ajeno
        $this->assertSame(0, Pago::count());
    }

    public function test_el_pago_simulado_funciona_fuera_de_produccion(): void
    {
        config()->set('pagos.pasarela', 'simulada');
        app()->forgetInstance(Pasarela::class);

        $html = $this->withSession([PedidoController::SESION => [$this->pedido->uuid]])->post(route('pedidos.es.pagar', ['pedido' => $this->pedido->uuid]))->assertOk()->getContent();
        preg_match('/action="([^"]+)"/', $html, $m);
        $pagina = $this->get(html_entity_decode($m[1]))->assertOk()->assertSee('Pago correcto')->getContent();
        preg_match('/class="ok" href="([^"]+)"/', $pagina, $ok);
        $this->get(html_entity_decode($ok[1]))->assertSessionHas('estado_pedido', __('pagos.cobrado'));

        $this->assertSame(E::Pagado, $this->pedido->fresh()->estado);
    }

    public function test_ajustes_pagos_muestra_la_pasarela_sin_la_clave(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Administrador))->get(AjustesPagos::getUrl(panel: 'admin'))
            ->assertOk()->assertSee('999008881')->assertSee(route('pagos.aviso'))->assertSee('Configurada (no se muestra)')->assertDontSee(self::CLAVE);
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get(AjustesPagos::getUrl(panel: 'admin'))->assertForbidden();
    }
}
