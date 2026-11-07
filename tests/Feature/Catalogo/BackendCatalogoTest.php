<?php

namespace Tests\Feature\Catalogo;

use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Catalogo\Pages\ManageColores;
use App\Filament\Resources\Catalogo\Pages\ManageFamilias;
use App\Filament\Resources\Catalogo\Pages\ManageProveedores;
use App\Filament\Resources\Catalogo\Pages\ManageTallas;
use App\Filament\Resources\Catalogo\Pages\ManageZonasImpresion;
use App\Models\Color;
use App\Models\Familia;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Database\Seeders\CatalogoInicialSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BackendCatalogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
    }

    public static function secciones(): array
    {
        return [
            'familias' => ['/admin/familias', 'Familias'],
            'colores' => ['/admin/colores', 'Colores'],
            'tallas' => ['/admin/tallas', 'Tallas'],
            'zonas' => ['/admin/zonas-impresion', 'Zonas de impresión'],
            'proveedores' => ['/admin/proveedores', 'Proveedores'],
        ];
    }

    #[DataProvider('secciones')]
    public function test_el_gestor_abre_cada_seccion_y_produccion_no(string $url, string $titulo): void
    {
        $this->get($url)->assertOk()->assertSee('<h1', false)->assertSee($titulo);
        $this->get($url)->assertDontSee('Zonas De Impresión');

        $this->actingAs($this->usuarioConRol(Rol::Produccion))->get($url)->assertForbidden();
    }

    public function test_crear_una_familia_en_dos_idiomas(): void
    {
        Livewire::test(ManageFamilias::class)
            ->callAction('create', [
                'nombre' => ['es' => 'Camisetas', 'en' => 'T-shirts'],
                'slug' => ['es' => 'camisetas', 'en' => 't-shirts'],
                'plazo_base_dias' => 2,
            ])
            ->assertHasNoFormErrors();

        $familia = Familia::sole();
        $this->assertSame(['es' => 'Camisetas', 'en' => 'T-shirts'], $familia->getTranslations('nombre'));
    }

    public function test_sin_ingles_se_usa_el_espanol(): void
    {
        Livewire::test(ManageFamilias::class)
            ->callAction('create', ['nombre' => ['es' => 'Polos', 'en' => ''], 'slug' => ['es' => 'polos', 'en' => ''], 'plazo_base_dias' => 3])
            ->assertHasNoFormErrors();

        App::setLocale('en');
        $this->assertSame('Polos', Familia::sole()->nombre);
    }

    public function test_la_url_de_una_familia_no_se_repite_en_el_mismo_idioma(): void
    {
        Familia::factory()->create(['slug' => ['es' => 'camisetas', 'en' => 't-shirts']]);

        Livewire::test(ManageFamilias::class)
            ->callAction('create', ['nombre' => ['es' => 'Otra'], 'slug' => ['es' => 'camisetas'], 'plazo_base_dias' => 2])
            ->assertHasFormErrors(['slug.es' => 'unique']);
    }

    public function test_al_editar_aparecen_los_dos_idiomas(): void
    {
        $familia = Familia::factory()->create(['nombre' => ['es' => 'Sudaderas', 'en' => 'Sweatshirts']]);

        Livewire::test(ManageFamilias::class)
            ->mountAction(TestAction::make('edit')->table($familia))
            ->assertSchemaStateSet(['nombre' => ['es' => 'Sudaderas', 'en' => 'Sweatshirts']]);
    }

    public function test_las_medidas_de_las_zonas_se_escriben_en_cm_y_se_guardan_en_mm(): void
    {
        Livewire::test(ManageZonasImpresion::class)
            ->callAction('create', [
                'nombre' => ['es' => 'Frontal'], 'clave' => 'frontal', 'cara' => 'delantera',
                'ancho_max_mm' => 28.5, 'alto_max_mm' => 38,
            ])
            ->assertHasNoFormErrors();

        $zona = ZonaImpresion::sole();
        $this->assertSame(285, $zona->ancho_max_mm);
        $this->assertSame(380, $zona->alto_max_mm);
    }

    public function test_crear_color_talla_y_proveedor(): void
    {
        Livewire::test(ManageColores::class)->callAction('create', ['nombre' => ['es' => 'Negro'], 'codigo_hex' => '#1c1c1f'])->assertHasNoFormErrors();
        Livewire::test(ManageTallas::class)->callAction('create', ['nombre' => 'XL', 'orden' => 4])->assertHasNoFormErrors();
        Livewire::test(ManageProveedores::class)->callAction('create', ['nombre' => 'Textil SL', 'tipo_conector' => 'manual'])->assertHasNoFormErrors();

        $this->assertSame('#1C1C1F', Color::sole()->codigo_hex);
        $this->assertDatabaseHas('tallas', ['nombre' => 'XL']);
        $this->assertDatabaseHas('proveedores', ['nombre' => 'Textil SL']);
    }

    public function test_no_se_puede_borrar_lo_que_esta_en_uso(): void
    {
        $variante = Variante::factory()->create();
        $libre = Color::factory()->create();

        Livewire::test(ManageColores::class)
            ->assertActionHidden(TestAction::make('delete')->table($variante->color))
            ->callAction(TestAction::make('delete')->table($libre));

        Livewire::test(ManageFamilias::class)
            ->assertActionHidden(TestAction::make('delete')->table($variante->prenda->familia));

        $this->assertModelMissing($libre);
        $this->assertModelExists($variante->color);
    }

    public function test_una_familia_con_prendas_en_la_papelera_tampoco_se_borra(): void
    {
        $prenda = Prenda::factory()->create();
        $prenda->delete();

        Livewire::test(ManageFamilias::class)
            ->assertActionHidden(TestAction::make('delete')->table($prenda->familia));
    }

    public function test_los_datos_iniciales_se_cargan_sin_duplicar(): void
    {
        $this->seed(CatalogoInicialSeeder::class);
        $this->seed(CatalogoInicialSeeder::class);

        $this->assertSame(3, Familia::count());
        $this->assertSame(7, Talla::count());
        $this->assertSame(3, ZonaImpresion::count());
    }
}
