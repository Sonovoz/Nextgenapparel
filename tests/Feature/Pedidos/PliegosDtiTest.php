<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Produccion\PliegosDti;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\Produccion\PliegosDtiPagina;
use App\Models\Pedido;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Tests\TestCase;

/** Pliegos DTI (v2, D193): los diseños de los pedidos en producción, repetidos por prenda, en el film. */
class PliegosDtiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Pedido::DISCO);
        $png = new Imagick;
        $png->newImage(400, 300, new ImagickPixel('#2D4BFF'));
        $png->setImageFormat('png');

        $pedido = Pedido::factory()->create(['estado' => E::EnProduccion, 'numero' => 'PED-0042']);
        $linea = $pedido->lineas()->create(['tipo' => 'prenda', 'nombre' => ['es' => 'Camiseta'], 'unidades' => 15, 'prendas' => 0, 'impresion' => 0, 'descuento' => 0, 'cupon' => 0, 'neto' => 0, 'tipo_iva' => 2100, 'recargo' => 0]);
        Storage::disk(Pedido::DISCO)->put("{$pedido->uuid}/logo.png", $png->getImageBlob());
        $linea->impresiones()->create(['zona_nombre' => ['es' => 'Frontal'], 'cara' => 'delantera', 'archivo' => "{$pedido->uuid}/logo.png", 'ancho_mm' => 250, 'alto_mm' => 190, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);
        $linea->impresiones()->create(['zona_nombre' => ['es' => 'Espalda'], 'cara' => 'trasera', 'texto' => 'EQUIPO', 'fuente' => 0, 'tinta' => 'blanco', 'ancho_mm' => 200, 'alto_mm' => 60, 'desde_cuello_mm' => 100, 'desde_centro_mm' => 0]);
        Pedido::factory()->create(['estado' => E::Pagado]); // aún no en producción: fuera
    }

    public function test_cada_prenda_lleva_su_diseno_en_el_pliego_y_los_textos_van_aparte(): void
    {
        $plan = PliegosDti::plan();

        // 15 logos de 250 × 190 mm: 2 por fila en 580 mm y 5 filas por pliego de 1000 mm → 10 + 5.
        $this->assertSame([10, 5], array_map(fn ($p) => count($p['piezas']), $plan['pliegos']));
        $this->assertSame('PED-0042_L1_frontal-1', $plan['pliegos'][0]['piezas'][0]['id']);
        $this->assertSame(['PED-0042 L1 · Espalda · «EQUIPO» × 15'], $plan['textos']);
        $this->assertStringStartsWith('%PDF', PliegosDti::pdf());
    }

    public function test_quien_tiene_produccion_ve_el_resumen_y_descarga_el_pdf(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::Produccion))->get(PliegosDtiPagina::getUrl())->assertOk()->assertSee('Textos para imprimir aparte');
        $this->get(route('backend.produccion.pliegos'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get(route('backend.produccion.pliegos'))->assertOk(); // el gestor también tiene Producción
    }
}
