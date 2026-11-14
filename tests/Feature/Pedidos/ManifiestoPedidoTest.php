<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Pedidos\ManifiestoPedido;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Pedidos\PedidoResource;
use App\Models\Color;
use App\Models\Pack;
use App\Models\Pedido;
use App\Models\Prenda;
use App\Models\Talla;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** D241: manifiesto del pedido (prendas × color × talla y referencias de impresión) y su descarga en ZIP. */
class ManifiestoPedidoTest extends TestCase
{
    use RefreshDatabase;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Storage::fake(Pedido::DISCO);
        $s = Talla::factory()->create(['nombre' => 'S', 'orden' => 1]);
        $m = Talla::factory()->create(['nombre' => 'M', 'orden' => 2]);
        $l = Talla::factory()->create(['nombre' => 'L', 'orden' => 3]);
        $sudadera = Prenda::factory()->create(['nombre' => ['es' => 'Sudadera Urban'], 'referencia_proveedor' => 'SU-300']);
        $camiseta = Prenda::factory()->create(['nombre' => ['es' => 'Camiseta Essential']]);
        $negro = Color::factory()->create(['nombre' => ['es' => 'Negro'], 'codigo_hex' => '#111111']);

        $this->pedido = Pedido::factory()->create(['estado' => E::Pagado, 'numero' => 'PED-0004', 'nombre_fiscal' => 'Diego Martín', 'nif' => '12345678Z',
            'correo' => 'diego@ejemplo.es', 'direccion_envio' => ['nombre' => 'Diego', 'direccion' => 'Calle Mayor 1', 'codigo_postal' => '40320', 'localidad' => 'Cantalejo', 'pais' => 'ES', 'telefono' => '600000000']]);
        $linea = $this->pedido->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $sudadera->id, 'nombre' => ['es' => 'Sudadera Urban'], 'color_nombre' => ['es' => 'Blanco'], 'color_hex' => '#FFFFFF',
            'tallas' => [['talla_id' => $m->id, 'nombre' => 'M', 'unidades' => 2], ['talla_id' => $s->id, 'nombre' => 'S', 'unidades' => 2]],
            'unidades' => 4, 'prendas' => 0, 'impresion' => 0, 'descuento' => 0, 'cupon' => 0, 'neto' => 6400, 'tipo_iva' => 2100, 'recargo' => 0]);
        Storage::disk(Pedido::DISCO)->put("{$this->pedido->uuid}/spyro.png", 'BYTES-DEL-CLIENTE');
        $linea->impresiones()->create(['zona_nombre' => ['es' => 'Frontal'], 'cara' => 'delantera', 'archivo' => "{$this->pedido->uuid}/spyro.png", 'nombre_original' => 'spyro.png',
            'ancho_mm' => 163, 'alto_mm' => 300, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0, 'ppp' => 96, 'nivel_calidad' => 'baja']);

        // Un pack: sus prendas y tallas salen en el manifiesto como filas propias.
        $pack = Pack::create(['nombre' => ['es' => 'Pack Equipo'], 'slug' => ['es' => 'pack-equipo'], 'precio' => 5000, 'reparto' => 'fijo']);
        $lp = $pack->lineas()->create(['prenda_id' => $camiseta->id, 'unidades' => 5, 'colores' => [$negro->id], 'reparto_tallas' => [$m->id => 3, $l->id => 2]]);
        $this->pedido->lineas()->create(['tipo' => 'pack', 'pack_id' => $pack->id, 'nombre' => ['es' => 'Pack Equipo'],
            'elecciones' => [$lp->id => ['prenda' => $camiseta->id, 'color' => $negro->id, 'tallas' => [$m->id => 3, $l->id => 2]]],
            'unidades' => 5, 'prendas' => 0, 'impresion' => 0, 'descuento' => 0, 'cupon' => 0, 'neto' => 5000, 'tipo_iva' => 2100, 'recargo' => 0]);
    }

    public function test_el_manifiesto_cuenta_prendas_por_color_y_talla_tambien_las_de_los_packs(): void
    {
        $filas = ManifiestoPedido::prendas($this->pedido);

        $this->assertSame([['Sudadera Urban', 'SU-300', 'Blanco', null, ['M' => 2, 'S' => 2], 4], ['Camiseta Essential', null, 'Negro', 'Pack Equipo', ['M' => 3, 'L' => 2], 5]],
            array_map(fn ($f) => [$f['prenda'], $f['referencia'], $f['color'], $f['pack'], $f['tallas'], $f['unidades']], $filas));
        $this->assertSame(['S' => 2, 'M' => 5, 'L' => 2], ManifiestoPedido::tallas($filas)); // en el orden de las tallas

        $impresion = ManifiestoPedido::impresiones($this->pedido)[0];
        $this->assertSame(['PED-0004_L1_frontal_16,3x30cm.png', '16,3 × 30 cm', '7,5 cm bajo el cuello · centrado'], [$impresion['archivo'], $impresion['medida'], $impresion['posicion']]);
    }

    public function test_la_ficha_muestra_el_manifiesto_y_descarga_el_zip_con_los_disenos_sin_modificar_y_el_pdf(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $this->get(PedidoResource::getUrl('view', ['record' => $this->pedido]))->assertOk()
            ->assertSee('Manifiesto de prendas')->assertSee('Total por talla')->assertSee('Pack: Pack Equipo')->assertSee('Descargar manifiesto de pedido');

        $respuesta = $this->get(route('backend.pedidos.manifiesto', ['pedido' => $this->pedido->uuid]));
        $respuesta->assertOk()->assertDownload('PED-0004_manifiesto.zip');

        $zip = new ZipArchive;
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $this->assertSame('BYTES-DEL-CLIENTE', $zip->getFromName('disenos/PED-0004_L1_frontal_16,3x30cm.png')); // tal cual, sin modificar
        $pdf = (string) $zip->getFromName('PED-0004_manifiesto.pdf');
        $this->assertStringStartsWith('%PDF', $pdf);
        $zip->close();
    }

    public function test_sin_permiso_para_ver_pedidos_no_hay_manifiesto(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Produccion)); // no ve los datos del cliente

        $this->get(route('backend.pedidos.manifiesto', ['pedido' => $this->pedido->uuid]))->assertNotFound();
    }
}
