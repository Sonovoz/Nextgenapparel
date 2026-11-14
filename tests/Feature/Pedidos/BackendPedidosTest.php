<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\AjustesGenerales;
use App\Filament\Resources\Pedidos\Pages\ListPedidos;
use App\Filament\Resources\Pedidos\Pages\ViewPedido;
use App\Filament\Resources\Pedidos\PedidoResource;
use App\Http\Controllers\PedidoController;
use App\Models\Ajuste;
use App\Models\Pedido;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Backend → Pedidos como en WooCommerce (paso 6.3). */
class BackendPedidosTest extends TestCase
{
    use RefreshDatabase;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Storage::fake(Pedido::DISCO);
        $this->pedido = Pedido::factory()->create(['estado' => E::Pagado, 'numero' => 'PED-0042', 'nombre_fiscal' => 'Estudio Luna']);
        $linea = $this->pedido->lineas()->create(['tipo' => 'prenda', 'nombre' => ['es' => 'Camiseta Essential'], 'color_nombre' => ['es' => 'Negro'],
            'tallas' => [['talla_id' => 1, 'nombre' => 'M', 'unidades' => 15]], 'unidades' => 15, 'prendas' => 6000, 'impresion' => 4500,
            'descuento' => 0, 'cupon' => 0, 'neto' => 10500, 'tipo_iva' => 2100, 'recargo' => 0]);
        Storage::disk(Pedido::DISCO)->put("{$this->pedido->uuid}/logo.png", 'PNG');
        $linea->impresiones()->create(['zona_nombre' => ['es' => 'Frontal'], 'cara' => 'delantera', 'archivo' => "{$this->pedido->uuid}/logo.png",
            'nombre_original' => 'logo.png', 'ancho_mm' => 100, 'alto_mm' => 100, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0, 'ppp' => 305, 'nivel_calidad' => 'correcta']);
    }

    public static function roles(): array
    {
        return [
            'superadministrador' => [Rol::Superadministrador, 200],
            'administrador' => [Rol::Administrador, 200],
            'gestor de la tienda' => [Rol::GestorTienda, 200],
            'producción' => [Rol::Produccion, 403],
        ];
    }

    #[DataProvider('roles')]
    public function test_la_seccion_pedidos_es_para_quien_tiene_su_permiso(Rol $rol, int $estado): void
    {
        $this->actingAs($this->usuarioConRol($rol));

        $this->get(PedidoResource::getUrl('index'))->assertStatus($estado);
        $this->get(PedidoResource::getUrl('view', ['record' => $this->pedido]))->assertStatus($estado);
        // El original del diseño: con permiso se descarga (y queda apuntado); sin él, como si no existiera.
        $original = $this->get(route('backend.pedidos.original', ['pedido' => $this->pedido->uuid, 'impresion' => $this->pedido->lineas()->sole()->impresiones()->sole()->id]));
        $estado === 200 ? $original->assertOk()->assertDownload('PED-0042_Frontal_logo.png') : $original->assertNotFound();
    }

    public function test_la_ficha_muestra_lo_que_necesita_el_equipo(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        $this->get(PedidoResource::getUrl('view', ['record' => $this->pedido]))->assertOk()
            ->assertSee('Pedido PED-0042')->assertSee('Camiseta Essential')->assertSee('M × 15')->assertSee('descargar original')
            ->assertSee('305 ppp')->assertSee('Maqueta pendiente')->assertSee('El cliente ve: «Pedido recibido»');
        Livewire::test(ListPedidos::class)->assertCanSeeTableRecords([$this->pedido])->assertSee('Pagado');
    }

    /** D240: los pagados, en la primera pestaña; pendientes de pago y cancelados, aparte. */
    public function test_el_listado_separa_pagados_pendientes_y_cancelados(): void
    {
        $pendiente = Pedido::factory()->create(['estado' => E::PendientePago, 'numero' => 'PED-0043']);
        $cancelado = Pedido::factory()->create(['estado' => E::Cancelado, 'numero' => 'PED-0044']);
        $enviado = Pedido::factory()->create(['estado' => E::Enviado, 'numero' => 'PED-0045']);
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(ListPedidos::class)->assertSet('activeTab', 'pagados')
            ->assertCanSeeTableRecords([$this->pedido, $enviado])->assertCanNotSeeTableRecords([$pendiente, $cancelado])
            ->set('activeTab', 'pendientes')->assertCanSeeTableRecords([$pendiente])->assertCanNotSeeTableRecords([$this->pedido, $cancelado])
            ->set('activeTab', 'cancelados')->assertCanSeeTableRecords([$cancelado])->assertCanNotSeeTableRecords([$this->pedido, $pendiente]);
    }

    /** D240: aviso arriba del panel mientras haya pedidos pagados sin empezar; Producción no lo ve. */
    public function test_el_panel_avisa_de_los_pedidos_nuevos_pagados(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $this->get(PedidoResource::getUrl('index'))->assertOk()->assertSee('Nuevo pedido pagado: PED-0042')->assertSee('Ver pedido');

        Pedido::factory()->create(['estado' => E::Pagado, 'numero' => 'PED-0046']);
        $this->get(PedidoResource::getUrl('index'))->assertSee('2 pedidos nuevos pagados')->assertSee('PED-0046, PED-0042');

        Pedido::query()->update(['estado' => E::EnProduccion->value]);
        $this->get(PedidoResource::getUrl('index'))->assertDontSee('id="aviso-pedidos-nuevos"', false);
    }

    public function test_cambiar_el_estado_desde_la_ficha_queda_en_las_notas(): void
    {
        $this->actingAs($quien = $this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(ViewPedido::class, ['record' => $this->pedido->getRouteKey()])
            ->callAction('estado', data: ['estado' => E::DisenoEnRevision->value, 'nota' => 'Revisamos el logo'])
            ->assertNotified('Estado cambiado');

        $this->assertSame(E::DisenoEnRevision, $this->pedido->fresh()->estado);
        $this->assertSame([$quien->id, 'Revisamos el logo'], [$this->pedido->historial()->sole()->user_id, $this->pedido->historial()->sole()->nota]);
    }

    /** Las notas para el cliente las ve en su pedido; las privadas, no. */
    public function test_notas_privadas_y_para_el_cliente(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $ficha = Livewire::test(ViewPedido::class, ['record' => $this->pedido->getRouteKey()]);
        $ficha->callAction('nota', data: ['tipo' => 'privada', 'texto' => 'Ojo: cliente nuevo']);
        $ficha->callAction('nota', data: ['tipo' => 'cliente', 'texto' => 'Tu pedido sale el lunes']);

        $this->flushSession();
        $this->withSession([PedidoController::SESION => [$this->pedido->uuid]])->get('/es/pedidos/'.$this->pedido->uuid)
            ->assertSee('Novedades de tu pedido')->assertSee('Tu pedido sale el lunes')->assertDontSee('Ojo: cliente nuevo');
    }

    public function test_en_bloque_cambia_los_que_puede_y_explica_los_demas(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $enProduccion = Pedido::factory()->create(['estado' => E::EnProduccion, 'numero' => 'PED-0043']);

        Livewire::test(ListPedidos::class)
            ->callTableBulkAction('cambiar_estado', [$this->pedido, $enProduccion], data: ['estado' => E::Cancelado->value])
            ->assertNotified('1 pedido cambiado');

        $this->assertSame([E::Cancelado, E::EnProduccion], [$this->pedido->fresh()->estado, $enProduccion->fresh()->estado]);
    }

    public function test_la_fecha_estimada_se_cambia_a_mano_y_queda_anotada(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(ViewPedido::class, ['record' => $this->pedido->getRouteKey()])
            ->callAction('fecha', data: ['fecha_estimada_entrega' => '2026-12-01', 'motivo' => 'Falta tinta dorada']);

        $this->assertSame('2026-12-01', $this->pedido->fresh()->fecha_estimada_entrega->toDateString());
        $this->assertSame('Entrega estimada: 01/12/2026 · Falta tinta dorada', $this->pedido->historial()->sole()->nota);
    }

    public function test_los_ajustes_de_pedidos_se_guardan(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(AjustesGenerales::class)
            ->fillForm(['pedidos_prefijo' => 'NG-', 'pedidos_dias_sin_pago' => 10, 'pedidos_tramos' => [['desde' => 200, 'dias' => 4], ['desde' => 50, 'dias' => 2]]])
            ->callAction('guardar')->assertNotified('Ajustes guardados');

        $this->assertSame(['NG-', 10, [['desde' => 50, 'dias' => 2], ['desde' => 200, 'dias' => 4]]],
            [Ajuste::obtener('pedidos.prefijo'), Ajuste::obtener('pedidos.dias_sin_pago'), Ajuste::obtener('pedidos.plazo_tramos')]);
    }
}
