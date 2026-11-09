<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Produccion\HojaCompra;
use App\Dominio\Produccion\OrdenTrabajo;
use App\Dominio\Produccion\PaqueteProduccion;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\Produccion\HojaCompra as PaginaHojaCompra;
use App\Filament\Pages\Produccion\OrdenesTrabajo;
use App\Models\Color;
use App\Models\Pack;
use App\Models\Pedido;
use App\Models\Prenda;
use App\Models\Proveedor;
use App\Models\Talla;
use App\Models\Variante;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

/** Documentos de producción (paso 6.5b): orden de trabajo, paquete ZIP y hoja de compra. */
class ProduccionTest extends TestCase
{
    use RefreshDatabase;

    private Pedido $pedido;

    private Prenda $prenda;

    private Color $negro;

    private Talla $m;

    private Talla $l;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Storage::fake(Pedido::DISCO);

        $this->prenda = Prenda::factory()->create(['proveedor_id' => Proveedor::factory()->create(['nombre' => 'Textil Norte'])->id, 'referencia_proveedor' => 'TN-150']);
        $this->negro = Color::factory()->create(['nombre' => ['es' => 'Negro']]);
        $this->m = Talla::factory()->create(['nombre' => 'M', 'orden' => 2]);
        $this->l = Talla::factory()->create(['nombre' => 'L', 'orden' => 3]);
        Variante::factory()->create(['prenda_id' => $this->prenda->id, 'color_id' => $this->negro->id, 'talla_id' => $this->m->id, 'sku' => 'CAM-N-M', 'stock' => 12]);
        Variante::factory()->create(['prenda_id' => $this->prenda->id, 'color_id' => $this->negro->id, 'talla_id' => $this->l->id, 'sku' => 'CAM-N-L', 'stock' => 0]);

        $this->pedido = $this->pedidoCon(E::Pagado, 'PED-0042', [$this->m->id => 15, $this->l->id => 5]);
        Storage::disk(Pedido::DISCO)->put("{$this->pedido->uuid}/logo.png", 'PNG');
        $this->pedido->lineas[0]->impresiones()->create(['zona_nombre' => ['es' => 'Frontal'], 'cara' => 'delantera', 'archivo' => "{$this->pedido->uuid}/logo.png",
            'nombre_original' => 'mi logo.png', 'ancho_mm' => 250, 'alto_mm' => 120, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0, 'ppp' => 305, 'nivel_calidad' => 'correcta']);
        $this->pedido->lineas[0]->impresiones()->create(['zona_nombre' => ['es' => 'Espalda'], 'cara' => 'trasera', 'texto' => 'EQUIPO', 'fuente' => 2, 'tinta' => 'blanca',
            'ancho_mm' => 200, 'alto_mm' => 60, 'desde_cuello_mm' => 100, 'desde_centro_mm' => 0]);
    }

    /** @param  array<int, int>  $tallas */
    private function pedidoCon(E $estado, string $numero, array $tallas): Pedido
    {
        $pedido = Pedido::factory()->create(['estado' => $estado, 'numero' => $numero]);
        $pedido->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $this->prenda->id, 'color_id' => $this->negro->id, 'nombre' => ['es' => 'Camiseta Essential'], 'color_nombre' => ['es' => 'Negro'],
            'tallas' => collect($tallas)->map(fn ($u, $id) => ['talla_id' => $id, 'nombre' => Talla::find($id)->nombre, 'unidades' => $u])->values()->all(),
            'unidades' => array_sum($tallas), 'prendas' => 0, 'impresion' => 0, 'descuento' => 0, 'cupon' => 0, 'neto' => 0, 'tipo_iva' => 2100, 'recargo' => 0]);

        return $pedido->load('lineas');
    }

    public function test_los_disenos_se_nombran_por_pedido_linea_y_zona(): void
    {
        $impresion = $this->pedido->lineas[0]->impresiones()->first();

        $this->assertSame('PED-0042_L1_frontal.png', OrdenTrabajo::nombreArchivo($this->pedido, 1, $impresion));
    }

    public function test_el_paquete_lleva_los_disenos_renombrados_y_la_orden_de_trabajo(): void
    {
        $zip = new ZipArchive;
        $zip->open($ruta = PaqueteProduccion::crear($this->pedido->fresh()));
        $archivos = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $archivos[] = $zip->getNameIndex($i);
        }

        $this->assertEqualsCanonicalizing(['disenos/PED-0042_L1_frontal.png', 'PED-0042_orden-de-trabajo.pdf'], $archivos);
        $this->assertSame('PNG', $zip->getFromName('disenos/PED-0042_L1_frontal.png'));
        $this->assertStringStartsWith('%PDF', (string) $zip->getFromName('PED-0042_orden-de-trabajo.pdf'));
        $zip->close();
        @unlink($ruta);
    }

    /**
     * Hoja de compra: solo pedidos pagados sin producir; suma prendas sueltas y packs por
     * variante, y «comprar» descuenta el stock.
     */
    public function test_la_hoja_de_compra_suma_por_variante_y_descuenta_el_stock(): void
    {
        $this->pedidoCon(E::DisenoEnRevision, 'PED-0043', [$this->m->id => 3]);
        $this->pedidoCon(E::PendientePago, 'PED-0044', [$this->m->id => 50]);
        $this->pedidoCon(E::EnProduccion, 'PED-0045', [$this->m->id => 50]);
        $pack = Pack::create(['nombre' => ['es' => 'Pack'], 'slug' => ['es' => 'pack'], 'precio' => 5000, 'reparto' => 'fijo']);
        $lineaPack = $pack->lineas()->create(['prenda_id' => $this->prenda->id, 'unidades' => 4, 'colores' => [$this->negro->id], 'reparto_tallas' => [$this->l->id => 4]]);
        Pedido::factory()->create(['estado' => E::Pagado, 'numero' => 'PED-0046'])->lineas()->create(['tipo' => 'pack', 'pack_id' => $pack->id, 'nombre' => ['es' => 'Pack'],
            'elecciones' => [$lineaPack->id => ['prenda' => $this->prenda->id, 'color' => $this->negro->id, 'tallas' => [$this->l->id => 4]]],
            'unidades' => 4, 'prendas' => 0, 'impresion' => 0, 'descuento' => 0, 'cupon' => 0, 'neto' => 0, 'tipo_iva' => 2100, 'recargo' => 0]);

        $filas = collect(HojaCompra::filas())->keyBy('talla');

        $this->assertSame(['M', 'L'], $filas->keys()->all()); // en el orden de las tallas, no alfabético
        $this->assertSame(['Textil Norte', 'TN-150', 'CAM-N-M', 18, 12, 6, ['PED-0042', 'PED-0043']],
            [$filas['M']['proveedor'], $filas['M']['referencia'], $filas['M']['sku'], $filas['M']['necesarias'], $filas['M']['stock'], $filas['M']['comprar'], $filas['M']['pedidos']]);
        $this->assertSame([9, 0, 9, ['PED-0042', 'PED-0046']], [$filas['L']['necesarias'], $filas['L']['stock'], $filas['L']['comprar'], $filas['L']['pedidos']]);
        $this->assertStringContainsString('"Textil Norte";"Camiseta Essential";TN-150;Negro;M;CAM-N-M;18;12;6;"PED-0042 PED-0043"', str_replace("\xEF\xBB\xBF", '', HojaCompra::csv()));
    }

    public static function roles(): array
    {
        return [
            'producción' => [Rol::Produccion],
            'gestor de la tienda' => [Rol::GestorTienda],
            'administrador' => [Rol::Administrador],
        ];
    }

    #[DataProvider('roles')]
    public function test_produccion_descarga_sus_documentos(Rol $rol): void
    {
        $this->actingAs($this->usuarioConRol($rol));

        $this->get(OrdenesTrabajo::getUrl())->assertOk()->assertSee('PED-0042');
        $this->get(PaginaHojaCompra::getUrl())->assertOk()->assertSee('CAM-N-M');
        $this->get(route('backend.produccion.orden', $this->pedido))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('backend.produccion.paquete', $this->pedido))->assertOk()->assertDownload('PED-0042_produccion.zip');
        $this->get(route('backend.produccion.hoja'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_la_lista_de_ordenes_no_ensena_datos_del_cliente_ni_pedidos_sin_pagar(): void
    {
        $this->pedido->update(['nombre_fiscal' => 'Estudio Luna', 'correo' => 'luna@estudio.es']);
        $this->pedidoCon(E::PendientePago, 'PED-0050', [$this->m->id => 1]);
        $this->actingAs($this->usuarioConRol(Rol::Produccion));

        Livewire::test(OrdenesTrabajo::class)->assertCanSeeTableRecords([$this->pedido])
            ->assertDontSee('Estudio Luna')->assertDontSee('luna@estudio.es')->assertDontSee('PED-0050');
    }

    public function test_sin_la_seccion_produccion_o_sin_entrar_al_panel_no_hay_documentos(): void
    {
        $sin = $this->usuarioConRol(Rol::Produccion);
        $sin->syncRoles([]);
        $this->actingAs($sin);
        foreach ([route('backend.produccion.orden', $this->pedido), route('backend.produccion.paquete', $this->pedido), route('backend.produccion.hoja')] as $url) {
            $this->get($url)->assertNotFound();
        }

        auth()->logout();
        $this->get(route('backend.produccion.hoja'))->assertNotFound();
    }
}
