<?php

namespace Tests\Feature\Catalogo;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\TipoFoto;
use App\Dominio\Disenos\Calidad;
use App\Dominio\Precios\Formato;
use App\Dominio\Precios\Iva;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\AjustesGenerales;
use App\Models\Ajuste;
use App\Models\Color;
use App\Models\Foto;
use App\Models\FotoZona;
use App\Models\Prenda;
use Database\Seeders\EjemplosCatalogoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class TiendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Foto::DISCO);
        $this->seed(EjemplosCatalogoSeeder::class);
    }

    private function camiseta(): Prenda
    {
        return Prenda::where('slug->es', 'camiseta-essential')->sole();
    }

    // ── IVA y formato ─────────────────────────────────────────────────

    #[DataProvider('ivas')]
    public function test_el_iva_se_calcula_en_centimos_redondeando_al_centimo(int $sinIva, int $tipo, int $conIva): void
    {
        $this->assertSame($conIva, Iva::conIva($sinIva, $tipo));
    }

    public static function ivas(): array
    {
        return [
            '4,20 € al 21 %' => [420, 2100, 508],   // 508,2
            '7,50 € al 21 %' => [750, 2100, 908],   // 907,5 → hacia arriba
            '13,90 € al 21 %' => [1390, 2100, 1682], // 1681,9
            '1 céntimo' => [1, 2100, 1],
            'al 10 %' => [1000, 1000, 1100],
            'tipo con decimales (5,2 %)' => [10000, 520, 10520],
            'sin IVA' => [999, 0, 999],
        ];
    }

    public function test_los_importes_se_escriben_segun_el_idioma(): void
    {
        $this->assertSame('1.234,56 €', Formato::euros(123456, 'es'));
        $this->assertSame('€1,234.56', Formato::euros(123456, 'en'));
    }

    // ── Ajustes → General ─────────────────────────────────────────────

    public function test_el_iva_se_cambia_en_ajustes_y_queda_en_la_actividad(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(AjustesGenerales::class)
            ->assertSchemaStateSet(['iva_general' => 21])
            ->fillForm(['nombre' => 'NextGen Apparel', 'iva_general' => 10])
            ->callAction('guardar')
            ->assertNotified('Ajustes guardados');

        $this->assertSame(1000, Ajuste::obtener('impuestos.iva_general'));
        $this->assertSame('IVA general cambiado', Activity::latest('id')->value('description'));
        $this->get('/es/camisetas')->assertSee('4,62 €'); // 4,20 + 10 %
    }

    public function test_los_umbrales_de_calidad_se_cambian_en_ajustes_y_deben_ir_en_orden(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(AjustesGenerales::class)
            ->assertSchemaStateSet(['correcto' => 300, 'aceptable' => 200, 'minimo' => 150])
            ->fillForm(['correcto' => 200, 'aceptable' => 250])
            ->callAction('guardar')
            ->assertHasFormErrors(['correcto']);

        Livewire::test(AjustesGenerales::class)
            ->fillForm(['correcto' => 280, 'aceptable' => 180, 'minimo' => 120])
            ->callAction('guardar')
            ->assertNotified('Ajustes guardados');

        $this->assertSame(['correcto' => 280, 'aceptable' => 180, 'minimo' => 120], Calidad::umbrales());
        $this->assertSame('Umbrales de calidad cambiados', Activity::latest('id')->value('description'));
    }

    public function test_el_gestor_no_entra_en_ajustes(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get('/admin/ajustes-generales')->assertForbidden();
    }

    // ── Tienda ────────────────────────────────────────────────────────

    public function test_la_portada_muestra_las_familias(): void
    {
        $this->get('/es')->assertOk()->assertSeeInOrder(['Nuestras prendas', 'Camisetas', 'Polos', 'Sudaderas']);
        $this->get('/en')->assertOk()->assertSeeInOrder(['Our garments', 'T-shirts', 'Polo shirts', 'Sweatshirts']);
    }

    public function test_el_listado_muestra_las_prendas_con_precio_con_iva(): void
    {
        $this->get('/es/camisetas')->assertOk()->assertSee('Camiseta Essential')->assertSee('Desde 5,08 €')->assertSee('IVA incluido');
        $this->get('/en/t-shirts')->assertOk()->assertSee('Essential T-shirt')->assertSee('From €5.08')->assertSee('VAT included');
    }

    public function test_las_prendas_ocultas_en_papelera_o_sin_variaciones_no_aparecen(): void
    {
        $camiseta = $this->camiseta();
        $camiseta->update(['activa' => false]);
        $this->get('/es/camisetas')->assertDontSee('Camiseta Essential');
        $this->get('/es/camisetas/camiseta-essential')->assertNotFound();

        $camiseta->update(['activa' => true]);
        $camiseta->delete();
        $this->get('/es/camisetas/camiseta-essential')->assertNotFound();

        $camiseta->restore();
        $camiseta->variantes()->update(['activa' => false]);
        $this->get('/es/camisetas/camiseta-essential')->assertNotFound();
    }

    public function test_la_ficha_muestra_precio_colores_tallas_zonas_y_guia(): void
    {
        $this->get('/es/camisetas/camiseta-essential')->assertOk()
            ->assertSee('<h1>Camiseta Essential</h1>', false)
            ->assertSee('5,08 €')
            ->assertSee('aria-label="Blanco"', false)->assertSee('aria-label="Negro"', false)
            ->assertSeeInOrder(['XS', 'S', 'M', 'L', 'XL', 'XXL'])
            ->assertSee('Hasta 28 × 38 cm')
            ->assertSee('Guía de tallas')->assertSee('72 cm')
            ->assertSee('100 % algodón peinado')
            ->assertSee('Producción: 2 días laborables');
    }

    public function test_la_ficha_en_ingles_usa_su_url_y_textos(): void
    {
        $this->get('/en/t-shirts/essential-t-shirt')->assertOk()
            ->assertSee('Essential T-shirt')->assertSee('100% combed cotton')->assertSee('Size guide')->assertSee('Up to 28 × 38 cm');
    }

    public function test_el_selector_de_idioma_lleva_a_la_misma_prenda_traducida(): void
    {
        $this->get('/es/camisetas/camiseta-essential')
            ->assertSee('hreflang="en" href="'.url('/en/t-shirts/essential-t-shirt').'"', false)
            ->assertSee('hreflang="es" href="'.url('/es/camisetas/camiseta-essential').'"', false);
    }

    public function test_sin_url_en_ingles_se_usa_la_espanola(): void
    {
        $this->camiseta()->forgetTranslation('slug', 'en')->save();

        $this->get('/en/t-shirts/camiseta-essential')->assertOk()->assertSee('Essential T-shirt');
    }

    public function test_la_ficha_lleva_las_maquetas_y_sus_zonas_para_el_visor(): void
    {
        $camiseta = $this->camiseta();
        $negro = $camiseta->variantes()->with('color')->get()->firstWhere('color.codigo_hex', '#1C1C1F')->color;
        $foto = Foto::create(['prenda_id' => $camiseta->id, 'color_id' => $negro->id, 'tipo' => TipoFoto::Maqueta, 'cara' => Cara::Delantera, 'ruta' => 'catalogo/1/negro.webp']);
        FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => $camiseta->zonas()->where('clave', 'frontal')->value('zonas_impresion.id'), 'x' => 3000, 'y' => 2600, 'ancho' => 4000, 'alto' => 4200]);

        $html = $this->get('/es/camisetas/camiseta-essential')->assertOk()->getContent();
        preg_match("/data-colores='([^']+)'/", $html, $coincidencia);
        $colores = json_decode(html_entity_decode($coincidencia[1]), true);

        $negroEnVisor = collect($colores)->firstWhere('nombre', 'Negro');
        $this->assertStringEndsWith('catalogo/1/negro.webp', $negroEnVisor['caras']['delantera']['url']);
        $zonaFrontal = $camiseta->zonas()->where('clave', 'frontal')->value('zonas_impresion.id');
        $this->assertSame(['id' => $zonaFrontal, 'nombre' => 'Frontal', 'ancho_mm' => 280, 'alto_mm' => 380, 'x' => 30, 'y' => 26, 'ancho' => 40, 'alto' => 42], $negroEnVisor['caras']['delantera']['zonas'][0]);
        $this->assertNull($negroEnVisor['caras']['trasera']);
    }

    public function test_la_ficha_carga_el_visor_y_el_archivo_existe(): void
    {
        $this->get('/es/camisetas/camiseta-essential')->assertSee('js/visor.js?v=', false);
        $this->assertStringContainsString("document.getElementById('visor')", file_get_contents(public_path('js/visor.js')));
    }

    public function test_tu_marca_aparece_solo_si_hay_maquetas_con_zonas(): void
    {
        $this->get('/es/camisetas/camiseta-essential')->assertDontSee('id="tu-marca"', false);

        $camiseta = $this->camiseta();
        $color = $camiseta->variantes()->first()->color_id;
        $foto = Foto::create(['prenda_id' => $camiseta->id, 'color_id' => $color, 'tipo' => TipoFoto::Maqueta, 'cara' => Cara::Delantera, 'ruta' => 'catalogo/1/x.webp']);
        $this->get('/es/camisetas/camiseta-essential')->assertDontSee('id="tu-marca"', false);

        FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => $camiseta->zonas()->first()->id, 'x' => 3000, 'y' => 2600, 'ancho' => 4000, 'alto' => 4200]);
        $this->get('/es/camisetas/camiseta-essential')
            ->assertSee('id="tu-marca"', false)
            ->assertSee('id="coleccion"', false)
            ->assertSee('¿Tienes logo? Míralo puesto.')
            ->assertSee('no lo subimos ni lo guardamos');
        $this->get('/en/t-shirts/essential-t-shirt')->assertSee('Got a logo? See it on.');
    }

    public function test_el_visor_recibe_los_umbrales_de_calidad_de_ajustes(): void
    {
        Ajuste::guardar('disenos.ppp_correcto', 280);
        $camiseta = $this->camiseta();
        $foto = Foto::create(['prenda_id' => $camiseta->id, 'color_id' => $camiseta->variantes()->first()->color_id, 'tipo' => TipoFoto::Maqueta, 'cara' => Cara::Delantera, 'ruta' => 'catalogo/1/x.webp']);
        FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => $camiseta->zonas()->first()->id, 'x' => 3000, 'y' => 2600, 'ancho' => 4000, 'alto' => 4200]);

        $html = $this->get('/es/camisetas/camiseta-essential')->assertSee('id="ajustador"', false)->assertSee('Ajusta tu diseño')->getContent();
        preg_match("/data-calidad='([^']+)'/", $html, $coincidencia);
        $calidad = json_decode(html_entity_decode($coincidencia[1]), true);

        $this->assertSame(['correcto' => 280, 'aceptable' => 200, 'minimo' => 150], $calidad['umbrales']);
        $this->assertSame('Calidad baja', $calidad['textos']['nivel_baja']);
    }

    public function test_los_textos_de_la_tienda_existen_en_los_dos_idiomas(): void
    {
        foreach (['tienda', 'disenos'] as $archivo) {
            $es = require lang_path("es/{$archivo}.php");
            $en = require lang_path("en/{$archivo}.php");

            $this->assertSame(array_keys($es), array_keys($en), $archivo);
            $this->assertNotContains('', $es);
            $this->assertNotContains('', $en);
        }
    }

    public function test_un_color_desactivado_desaparece_de_la_ficha_y_del_precio(): void
    {
        $camiseta = $this->camiseta();
        $blanco = Color::where('codigo_hex', '#FFFFFF')->first() ?? $camiseta->variantes()->with('color')->get()->firstWhere('color.nombre', 'Blanco')->color;
        // El blanco pasa a ser el más barato y luego se desactiva.
        $camiseta->variantes()->where('color_id', $blanco->id)->update(['precio_base' => 100]);
        $this->assertSame(100, $camiseta->precioDesde());

        $blanco->update(['activo' => false]);

        $this->assertSame(420, $camiseta->precioDesde());
        $html = $this->get('/es/camisetas/camiseta-essential')->assertOk()->getContent();
        preg_match("/data-colores='([^']+)'/", $html, $coincidencia);
        $this->assertSame(['Negro'], array_column(json_decode(html_entity_decode($coincidencia[1]), true), 'nombre'));
        $this->assertStringNotContainsString('aria-label="Blanco"', $html);
    }

    public function test_el_visor_solo_ofrece_las_zonas_activas_de_la_prenda(): void
    {
        $camiseta = $this->camiseta();
        $foto = Foto::create(['prenda_id' => $camiseta->id, 'color_id' => $camiseta->variantes()->first()->color_id, 'tipo' => TipoFoto::Maqueta, 'cara' => Cara::Delantera, 'ruta' => 'catalogo/1/x.webp']);
        foreach ($camiseta->zonas()->where('cara', 'delantera')->get() as $zona) {
            FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => $zona->id, 'x' => 3000, 'y' => 2600, 'ancho' => 2000, 'alto' => 2000]);
        }
        $camiseta->zonas()->where('clave', 'pecho')->first()->update(['activa' => false]);

        $html = $this->get('/es/camisetas/camiseta-essential')->getContent();
        preg_match("/data-colores='([^']+)'/", $html, $coincidencia);
        $zonas = collect(json_decode(html_entity_decode($coincidencia[1]), true))->pluck('caras.delantera.zonas')->filter()->flatten(1);

        $this->assertSame(['Frontal'], $zonas->pluck('nombre')->unique()->values()->all());
    }

    public function test_familias_y_prendas_inexistentes_dan_404(): void
    {
        $this->get('/es/gorras')->assertNotFound();
        $this->get('/es/camisetas/no-existe')->assertNotFound();
        $this->get('/es/polos/camiseta-essential')->assertNotFound();
    }

    public function test_la_portada_se_muestra_aunque_la_base_de_datos_no_responda(): void
    {
        $original = config('database.default');
        config(['database.connections.caida' => ['driver' => 'sqlite', 'database' => '/no/existe.sqlite'], 'database.default' => 'caida']);

        try {
            $respuesta = $this->get('/es');
        } finally {
            config(['database.default' => $original]);
            DB::purge('caida');
        }

        $respuesta->assertOk()->assertSee('Tu colección, impresa como la imaginas.')->assertDontSee('id="nuestras-prendas"', false);
    }

    public function test_la_tienda_sigue_fuera_del_indice_mientras_esta_en_construccion(): void
    {
        $this->get('/es/camisetas/camiseta-essential')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
