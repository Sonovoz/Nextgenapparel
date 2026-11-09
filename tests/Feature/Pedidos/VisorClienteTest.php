<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Pedidos\Pages\ListPedidos;
use App\Filament\Resources\Pedidos\PedidoResource;
use App\Http\Controllers\PedidoController;
use App\Models\Ajuste;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\Diseno;
use App\Models\Familia;
use App\Models\Pedido;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\User;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use App\Notifications\Pedidos\CancelacionSolicitada;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Visor del cliente (paso 6.6, D177): progreso, cancelar, repetir y «Mis pedidos». */
class VisorClienteTest extends TestCase
{
    use RefreshDatabase;

    private Pedido $pedido;

    private Prenda $prenda;

    private Color $negro;

    private Talla $m;

    private ZonaImpresion $frontal;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Pedido::DISCO);
        Storage::fake(GuardarDiseno::DISCO);
        Notification::fake();

        $this->negro = Color::factory()->create(['nombre' => ['es' => 'Negro']]);
        $this->m = Talla::factory()->create(['nombre' => 'M']);
        $this->frontal = ZonaImpresion::factory()->create(['nombre' => ['es' => 'Frontal'], 'ancho_max_mm' => 280, 'alto_max_mm' => 380, 'desde_cuello_mm' => 75]);
        $this->prenda = Prenda::factory()->for(Familia::factory()->create())->create(['nombre' => ['es' => 'Camiseta Essential']]);
        Variante::factory()->for($this->prenda)->create(['color_id' => $this->negro->id, 'talla_id' => $this->m->id]);
        $this->prenda->zonas()->attach($this->frontal->id, ['precio' => 300, 'coste' => 100]);

        $this->pedido = Pedido::factory()->create(['estado' => E::PendientePago, 'numero' => 'PED-0007', 'cupon' => null]);
        $this->pedido->historial()->create(['estado' => E::PendientePago]);
        $linea = $this->pedido->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $this->prenda->id, 'color_id' => $this->negro->id, 'nombre' => ['es' => 'Camiseta Essential'], 'color_nombre' => ['es' => 'Negro'],
            'tallas' => [['talla_id' => $this->m->id, 'nombre' => 'M', 'unidades' => 15]], 'unidades' => 15, 'prendas' => 0, 'impresion' => 0, 'descuento' => 0, 'cupon' => 0, 'neto' => 0, 'tipo_iva' => 2100, 'recargo' => 0]);
        Storage::disk(Pedido::DISCO)->put("{$this->pedido->uuid}/logo.png", self::png());
        $linea->impresiones()->create(['zona_impresion_id' => $this->frontal->id, 'zona_nombre' => ['es' => 'Frontal'], 'cara' => 'delantera', 'archivo' => "{$this->pedido->uuid}/logo.png",
            'nombre_original' => 'mi-logo.png', 'ancho_mm' => 100, 'alto_mm' => 100, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0, 'ppp' => 305, 'nivel_calidad' => 'correcta']);
    }

    /** Un PNG de verdad (1200 × 1200, fondo transparente) para que lo acepte el análisis de diseños. */
    private static function png(): string
    {
        $imagen = new Imagick;
        $imagen->newImage(1200, 1200, new ImagickPixel('transparent'));
        $dibujo = new ImagickDraw;
        $dibujo->setFillColor(new ImagickPixel('#2D4BFF'));
        $dibujo->rectangle(200, 200, 1000, 1000);
        $imagen->drawImage($dibujo);
        $imagen->setImageFormat('png');

        return $imagen->getImageBlob();
    }

    private function comoDueno(): self
    {
        return $this->withSession([PedidoController::SESION => [$this->pedido->uuid]]);
    }

    public function test_sin_pagar_se_cancela_al_momento(): void
    {
        $this->comoDueno()->post(PedidoController::url('cancelar', $this->pedido), ['motivo' => 'Me equivoqué de talla'])
            ->assertRedirect(PedidoController::url('ver', $this->pedido))->assertSessionHas('estado_pedido', __('pedidos.cancelar_cancelado'));

        $this->assertSame(E::Cancelado, $this->pedido->fresh()->estado);
        $this->assertStringContainsString('Me equivoqué de talla', (string) $this->pedido->historial()->get()->last()->nota);
        Notification::assertSentOnDemandTimes(CancelacionSolicitada::class, 0); // el equipo no tiene nada que hacer
    }

    public static function pagados(): array
    {
        return ['pagado' => [E::Pagado], 'diseño en revisión' => [E::DisenoEnRevision], 'requiere cambios' => [E::RequiereCambios]];
    }

    /** Pagado y sin producir: queda la petición para el equipo, con aviso por correo; el estado no cambia. */
    #[DataProvider('pagados')]
    public function test_pagado_queda_la_peticion_para_el_equipo(E $estado): void
    {
        Ajuste::guardar('tienda.correo_contacto', 'taller@nextgenapparel.test');
        $this->pedido->update(['estado' => $estado]);

        $this->comoDueno()->post(PedidoController::url('cancelar', $this->pedido), ['motivo' => 'Ya no lo necesito'])
            ->assertSessionHas('estado_pedido', __('pedidos.cancelar_solicitado'));

        $pedido = $this->pedido->fresh();
        $this->assertSame([$estado, 'Ya no lo necesito'], [$pedido->estado, $pedido->motivo_cancelacion]);
        $this->assertNotNull($pedido->cancelacion_solicitada_en);
        $this->assertStringContainsString('El cliente pide cancelar', $pedido->notas()->where('tipo', 'privada')->sole()->texto);
        Notification::assertSentOnDemand(CancelacionSolicitada::class, fn ($n, $canales, $destino) => $destino->routes['mail'] === 'taller@nextgenapparel.test');

        // Una sola petición: el visor ya no ofrece el botón y otra no cambia nada.
        $this->comoDueno()->get(PedidoController::url('ver', $this->pedido))->assertSee(__('pedidos.cancelacion_pedida', ['fecha' => $pedido->cancelacion_solicitada_en->locale('es')->isoFormat('LL')]))
            ->assertDontSee(__('pedidos.cancelar_pedir'));
        $this->comoDueno()->post(PedidoController::url('cancelar', $this->pedido))->assertSessionHas('estado_pedido', __('pedidos.cancelar_no_puede'));
        $this->assertSame(1, $pedido->notas()->count());
    }

    public static function sinCancelar(): array
    {
        return ['en producción' => [E::EnProduccion], 'enviado' => [E::Enviado], 'entregado' => [E::Entregado], 'cancelado' => [E::Cancelado]];
    }

    #[DataProvider('sinCancelar')]
    public function test_en_produccion_o_despues_no_se_cancela(E $estado): void
    {
        $this->pedido->update(['estado' => $estado]);

        $this->comoDueno()->get(PedidoController::url('ver', $this->pedido))->assertOk()->assertDontSee(__('pedidos.cancelar'))->assertDontSee(__('pedidos.cancelar_pedir'));
        $this->comoDueno()->post(PedidoController::url('cancelar', $this->pedido))->assertSessionHas('estado_pedido', __('pedidos.cancelar_no_puede'));
        $this->assertSame($estado, $this->pedido->fresh()->estado);
    }

    public function test_quien_no_ve_el_pedido_no_lo_cancela_ni_lo_repite(): void
    {
        $this->post(PedidoController::url('cancelar', $this->pedido))->assertNotFound();
        $this->post(PedidoController::url('repetir', $this->pedido))->assertNotFound();
        $this->assertSame(E::PendientePago, $this->pedido->fresh()->estado);
    }

    public function test_el_progreso_marca_los_hitos_con_su_fecha(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 3));
        $this->pedido->update(['estado' => E::EnProduccion]);
        $this->pedido->historial()->create(['estado' => E::Pagado]);
        $this->pedido->historial()->create(['estado' => E::EnProduccion]);

        $html = $this->comoDueno()->get(PedidoController::url('ver', $this->pedido))->assertOk()
            ->assertSeeInOrder([__('pedidos.paso_pedido'), __('pedidos.paso_pagado'), '3 nov', __('pedidos.paso_produccion'), '3 nov', __('pedidos.paso_enviado'), __('pedidos.paso_entregado')])
            ->assertDontSee(__('pedidos.pago_pronto'))->getContent();
        $this->assertSame(3, substr_count($html, 'class="hecho"'));
    }

    /**
     * Repetir: las mismas prendas, tallas y diseños en el carrito de quien repite; el logo vuelve a su
     * biblioteca desde la copia del pedido. Lo que ya no se vende se salta y se dice.
     */
    public function test_repetir_pone_lo_mismo_en_el_carrito_y_salta_lo_que_ya_no_se_vende(): void
    {
        $retirada = Prenda::factory()->for($this->prenda->familia)->create(['nombre' => ['es' => 'Sudadera retirada'], 'activa' => false]);
        $this->pedido->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $retirada->id, 'color_id' => $this->negro->id, 'nombre' => ['es' => 'Sudadera retirada'], 'color_nombre' => ['es' => 'Negro'],
            'tallas' => [['talla_id' => $this->m->id, 'nombre' => 'M', 'unidades' => 15]], 'unidades' => 15, 'prendas' => 0, 'impresion' => 0, 'descuento' => 0, 'cupon' => 0, 'neto' => 0, 'tipo_iva' => 2100, 'recargo' => 0]);

        $this->comoDueno()->post(PedidoController::url('repetir', $this->pedido))
            ->assertRedirect('/es/carrito')
            ->assertSessionHas('estado', trans_choice('pedidos.repetir_anadidas', 1, ['n' => 1]).' '.__('pedidos.repetir_omitidas', ['lineas' => 'Sudadera retirada · Negro']));

        $linea = Carrito::sole()->lineas()->with('impresiones')->sole();
        $this->assertSame([$this->prenda->id, $this->negro->id, [$this->m->id => 15]], [$linea->prenda_id, $linea->color_id, $linea->tallas]);
        $impresion = $linea->impresiones->sole();
        $diseno = Diseno::sole();
        $this->assertSame([$this->frontal->id, $diseno->id, 100, 75], [$impresion->zona_impresion_id, $impresion->diseno_id, $impresion->ancho_mm, $impresion->desde_cuello_mm]);
        $this->assertSame(['mi-logo.png', hash('sha256', self::png())], [$diseno->nombre_original, $diseno->sha256]);
    }

    public function test_repetir_dos_veces_no_duplica_el_diseno_en_la_biblioteca(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@ejemplo.com']);
        $this->pedido->update(['cliente_id' => Cliente::factory()->create(['user_id' => $usuario->id, 'correo' => 'ana@ejemplo.com'])->id]);

        $this->actingAs($usuario)->post(PedidoController::url('repetir', $this->pedido));
        $this->actingAs($usuario)->post(PedidoController::url('repetir', $this->pedido));

        $this->assertSame(1, Diseno::count());
        $this->assertSame(2, Carrito::sole()->lineas()->count());
    }

    public function test_mis_pedidos_lista_solo_los_suyos(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@ejemplo.com']);
        $cliente = Cliente::factory()->create(['user_id' => $usuario->id, 'correo' => 'ana@ejemplo.com']);
        $this->pedido->update(['cliente_id' => $cliente->id]);
        Pedido::factory()->create(['numero' => 'PED-0099']);

        $this->actingAs($usuario)->get('/es/cuenta')->assertOk()->assertSee('PED-0007')->assertSee(PedidoController::url('ver', $this->pedido))->assertDontSee('PED-0099');
        $this->actingAs($usuario)->get(PedidoController::url('ver', $this->pedido))->assertOk()->assertSee(__('pedidos.repetir'));
    }

    public function test_el_backend_ve_y_filtra_las_peticiones_de_cancelacion(): void
    {
        Filament::setCurrentPanel('admin');
        $this->pedido->update(['estado' => E::Pagado, 'cancelacion_solicitada_en' => now(), 'motivo_cancelacion' => 'Talla equivocada']);
        $otro = Pedido::factory()->create(['estado' => E::Pagado, 'numero' => 'PED-0008']);
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        $this->get(PedidoResource::getUrl('view', ['record' => $this->pedido]))->assertOk()->assertSee('El cliente pide cancelar')->assertSee('Talla equivocada');
        Livewire::test(ListPedidos::class)->filterTable('cancelacion')->assertCanSeeTableRecords([$this->pedido])->assertCanNotSeeTableRecords([$otro]);
    }
}
