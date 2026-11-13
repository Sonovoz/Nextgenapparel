<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Pedidos\LimpiarArchivosPedidos;
use App\Models\Ajuste;
use App\Models\ImpresionPedido;
use App\Models\Pedido;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** D230: los archivos de impresión de un pedido se guardan en custodia 90 días hábiles desde el envío. */
class LimpiarArchivosPedidosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Pedido::DISCO);
    }

    private function pedido(E $estado, CarbonImmutable $creado, ?CarbonImmutable $enviado = null): Pedido
    {
        $pedido = Pedido::factory()->create(['estado' => $estado]);
        $pedido->forceFill(['created_at' => $creado])->saveQuietly();
        if ($enviado !== null) {
            $pedido->historial()->create(['estado' => E::Enviado->value])->forceFill(['created_at' => $enviado])->saveQuietly();
        }
        $linea = $pedido->lineas()->create(['tipo' => 'prenda', 'nombre' => ['es' => 'Camiseta'], 'unidades' => 10, 'prendas' => 1000, 'impresion' => 500,
            'descuento' => 0, 'cupon' => 0, 'neto' => 1500, 'tipo_iva' => 2100, 'recargo' => 0]);
        Storage::disk(Pedido::DISCO)->put("{$pedido->uuid}/logo.png", 'PNG');
        Storage::disk(Pedido::DISCO)->put("{$pedido->uuid}/logo-mini.webp", 'WEBP');
        $linea->impresiones()->create(['zona_nombre' => ['es' => 'Frontal'], 'archivo' => "{$pedido->uuid}/logo.png", 'miniatura' => "{$pedido->uuid}/logo-mini.webp",
            'ancho_mm' => 100, 'alto_mm' => 100, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);

        return $pedido;
    }

    public function test_borra_los_originales_a_los_90_dias_habiles_del_envio_y_conserva_el_pedido(): void
    {
        CarbonImmutable::setTestNow('2026-11-13 10:00:00'); // viernes
        Ajuste::guardar('pedidos.dias_archivos', 90);
        // 90 días hábiles (lunes a viernes) son 18 semanas: enviado hace 18 semanas y un día, ya toca; hace 100 días naturales, todavía no.
        $viejo = $this->pedido(E::Entregado, CarbonImmutable::now()->subWeeks(20), enviado: CarbonImmutable::now()->subWeeks(18)->subDay());
        $reciente = $this->pedido(E::Enviado, CarbonImmutable::now()->subDays(110), enviado: CarbonImmutable::now()->subDays(100));
        $cancelado = $this->pedido(E::Cancelado, CarbonImmutable::now()->subWeeks(19));
        $enProduccion = $this->pedido(E::EnProduccion, CarbonImmutable::now()->subYear());

        $this->assertSame('2026-11-12', LimpiarArchivosPedidos::fechaBorrado($viejo)->toDateString());
        $this->artisan('pedidos:limpiar-archivos')->expectsOutputToContain('Pedidos limpiados: 2. Archivos borrados: 2.')->assertSuccessful();

        foreach ([$viejo, $cancelado] as $p) {
            $this->assertNotNull($p->fresh()->archivos_borrados_en);
            Storage::disk(Pedido::DISCO)->assertMissing("{$p->uuid}/logo.png");
            Storage::disk(Pedido::DISCO)->assertExists("{$p->uuid}/logo-mini.webp"); // la miniatura se queda
        }
        $this->assertNull(ImpresionPedido::whereHas('linea', fn ($q) => $q->where('pedido_id', $viejo->id))->value('archivo'));
        foreach ([$reciente, $enProduccion] as $p) {
            $this->assertNull($p->fresh()->archivos_borrados_en);
            Storage::disk(Pedido::DISCO)->assertExists("{$p->uuid}/logo.png");
        }
        $this->assertModelExists($viejo);
        $this->assertDatabaseHas('activity_log', ['description' => 'Archivos de impresión de pedidos antiguos borrados']);
    }

    public function test_el_cliente_ve_la_custodia_y_al_repetir_se_le_pide_volver_a_subirlo(): void
    {
        CarbonImmutable::setTestNow('2026-11-13 10:00:00');
        $pedido = $this->pedido(E::Entregado, CarbonImmutable::now()->subWeeks(20), enviado: CarbonImmutable::now()->subWeeks(19));
        (new LimpiarArchivosPedidos)();

        $this->assertStringContainsString('90 días hábiles', __('pedidos.custodia', ['dias' => 90]));
        $this->assertStringContainsString('sin posibilidad de recuperación', __('pedidos.custodia', ['dias' => 90]));
        $this->assertStringContainsString('vuelve a subir', mb_strtolower(__('carrito.archivos_caducados', ['dias' => 90])).' vuelve a subir');
        $this->assertNotNull($pedido->fresh()->archivos_borrados_en);
    }
}
