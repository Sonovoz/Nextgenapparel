<?php

namespace Tests\Feature\Catalogo;

use App\Dominio\Catalogo\DatoCatalogoInvalido;
use App\Dominio\Catalogo\GenerarVariantes;
use App\Dominio\Catalogo\Sku;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Concerns\CampoDinero;
use App\Filament\Resources\Catalogo\Prendas\Pages\CreatePrenda;
use App\Filament\Resources\Catalogo\Prendas\Pages\EditPrenda;
use App\Filament\Resources\Catalogo\Prendas\Pages\ListPrendas;
use App\Filament\Resources\Catalogo\Prendas\RelationManagers\MedidasRelationManager;
use App\Filament\Resources\Catalogo\Prendas\RelationManagers\VariantesRelationManager;
use App\Filament\Resources\Catalogo\Prendas\RelationManagers\ZonasRelationManager;
use App\Models\Color;
use App\Models\Familia;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PrendasTest extends TestCase
{
    use RefreshDatabase;

    private Prenda $prenda;

    private Color $negro;

    private Color $blanco;

    /** @var array<string, Talla> */
    private array $tallas = [];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        $familia = Familia::factory()->create(['nombre' => ['es' => 'Camisetas'], 'slug' => ['es' => 'camisetas']]);
        $this->prenda = Prenda::factory()->for($familia)->create(['nombre' => ['es' => 'Camiseta Essential', 'en' => 'Essential T-shirt']]);
        $this->negro = Color::factory()->create(['nombre' => ['es' => 'Negro'], 'orden' => 1]);
        $this->blanco = Color::factory()->create(['nombre' => ['es' => 'Blanco'], 'orden' => 0]);

        foreach (['S', 'M', 'L'] as $orden => $nombre) {
            $this->tallas[$nombre] = Talla::factory()->create(['nombre' => $nombre, 'orden' => $orden]);
        }
    }

    // ── SKU y generador ───────────────────────────────────────────────

    public function test_el_sku_sigue_el_formato_familia_prenda_color_talla(): void
    {
        $this->assertSame('CAM-ESSENTIAL-NEGRO-M', Sku::para($this->prenda, $this->negro, $this->tallas['M']));
    }

    public function test_el_sku_quita_tildes_y_no_se_repite(): void
    {
        $azul = Color::factory()->create(['nombre' => ['es' => 'Azul marino']]);
        Variante::factory()->create(['sku' => 'CAM-ESSENTIAL-AZUL-MARINO-S']);

        $this->assertSame('CAM-ESSENTIAL-AZUL-MARINO-S-2', Sku::para($this->prenda, $azul, $this->tallas['S']));
    }

    public function test_genera_todas_las_combinaciones_con_sus_valores(): void
    {
        $creadas = app(GenerarVariantes::class)($this->prenda, [$this->negro->id, $this->blanco->id], array_map(fn ($t) => $t->id, $this->tallas), 450, 260, 180, 5);

        $this->assertSame(6, $creadas);
        $variante = $this->prenda->variantes()->where('sku', 'CAM-ESSENTIAL-BLANCO-L')->sole();
        $this->assertSame([450, 260, 180, 5], [$variante->precio_base, $variante->coste, $variante->peso_gramos, $variante->stock]);
    }

    public function test_al_volver_a_generar_solo_crea_las_que_faltan_y_no_toca_las_existentes(): void
    {
        $generar = app(GenerarVariantes::class);
        $generar($this->prenda, [$this->negro->id], [$this->tallas['S']->id], 450, 260, 180);
        $this->prenda->variantes()->update(['precio_base' => 999]);

        $creadas = $generar($this->prenda, [$this->negro->id], [$this->tallas['S']->id, $this->tallas['M']->id], 450, 260, 180);

        $this->assertSame(1, $creadas);
        $this->assertSame(999, $this->prenda->variantes()->where('talla_id', $this->tallas['S']->id)->value('precio_base'));
    }

    public function test_el_generador_rechaza_valores_imposibles(): void
    {
        $this->expectException(DatoCatalogoInvalido::class);

        app(GenerarVariantes::class)($this->prenda, [$this->negro->id], [$this->tallas['S']->id], 450, 260, 0);
    }

    // ── Dinero ────────────────────────────────────────────────────────

    #[DataProvider('importes')]
    public function test_los_euros_se_convierten_a_centimos_sin_errores_de_redondeo(mixed $euros, int $centimos): void
    {
        $this->assertSame($centimos, CampoDinero::aCentimos($euros));
    }

    public static function importes(): array
    {
        return [
            'con punto' => ['12.50', 1250],
            'con coma' => ['12,34', 1234],
            '0,29 (28,999… en coma flotante)' => [0.29, 29],
            '19,99' => [19.99, 1999],
            'medio céntimo redondea hacia arriba' => ['4.005', 401],
            'entero' => [7, 700],
        ];
    }

    public function test_los_importes_se_muestran_en_formato_espanol(): void
    {
        $this->assertSame('1.234,50 €', CampoDinero::formatear(123450));
    }

    // ── Backend ───────────────────────────────────────────────────────

    public function test_crear_una_prenda_lleva_a_su_edicion(): void
    {
        Livewire::test(CreatePrenda::class)
            ->fillForm([
                'familia_id' => $this->prenda->familia_id,
                'nombre' => ['es' => 'Polo Classic', 'en' => 'Classic Polo'],
                'slug' => ['es' => 'polo-classic', 'en' => 'classic-polo'],
                'gramaje' => 200,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(EditPrenda::getUrl(['record' => Prenda::where('slug->es', 'polo-classic')->sole()]));
    }

    public function test_la_url_de_la_prenda_no_se_repite(): void
    {
        Livewire::test(CreatePrenda::class)
            ->fillForm(['familia_id' => $this->prenda->familia_id, 'nombre' => ['es' => 'Otra'], 'slug' => ['es' => $this->prenda->getTranslation('slug', 'es')]])
            ->call('create')
            ->assertHasFormErrors(['slug.es' => 'unique']);
    }

    public function test_generar_variaciones_desde_el_backend_guarda_centimos(): void
    {
        Livewire::test(VariantesRelationManager::class, ['ownerRecord' => $this->prenda, 'pageClass' => EditPrenda::class])
            ->callTableAction('generar', data: [
                'colores' => [$this->negro->id], 'tallas' => [$this->tallas['M']->id, $this->tallas['L']->id],
                'precio_base' => '4.50', 'coste' => '2,60', 'peso_gramos' => 180, 'stock' => 10,
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('2 variaciones creadas');

        $this->assertSame([450, 450], $this->prenda->variantes()->pluck('precio_base')->all());
        $this->assertSame(260, $this->prenda->variantes()->value('coste'));
    }

    public function test_cambiar_el_precio_de_una_variacion_queda_en_la_actividad(): void
    {
        $variante = Variante::factory()->for($this->prenda)->create(['precio_base' => 400]);
        Activity::query()->delete();

        Livewire::test(VariantesRelationManager::class, ['ownerRecord' => $this->prenda, 'pageClass' => EditPrenda::class])
            ->callAction(TestAction::make('edit')->table($variante), ['precio_base' => '5,25'])
            ->assertHasNoFormErrors();

        $this->assertSame(525, $variante->fresh()->precio_base);
        $this->assertSame('Variación modificada', Activity::latest('id')->value('description'));
    }

    public function test_un_importe_mal_escrito_se_rechaza_con_un_mensaje_claro(): void
    {
        $variante = Variante::factory()->for($this->prenda)->create(['precio_base' => 400]);

        foreach (['abc', '4,505', '-3'] as $importe) {
            Livewire::test(VariantesRelationManager::class, ['ownerRecord' => $this->prenda, 'pageClass' => EditPrenda::class])
                ->callAction(TestAction::make('edit')->table($variante), ['precio_base' => $importe])
                ->assertHasFormErrors(['precio_base' => 'regex']);
        }

        $this->assertSame(400, $variante->fresh()->precio_base);
    }

    public function test_cambiar_el_precio_de_varias_variaciones_a_la_vez(): void
    {
        $variantes = Variante::factory()->for($this->prenda)->count(3)->create(['precio_base' => 400]);

        Livewire::test(VariantesRelationManager::class, ['ownerRecord' => $this->prenda, 'pageClass' => EditPrenda::class])
            ->callTableBulkAction('cambiarPrecio', $variantes, data: ['precio_base' => '6'])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame([600, 600, 600], $this->prenda->variantes()->pluck('precio_base')->all());
    }

    public function test_anadir_una_zona_con_su_precio(): void
    {
        $zona = ZonaImpresion::factory()->create(['nombre' => ['es' => 'Frontal']]);

        Livewire::test(ZonasRelationManager::class, ['ownerRecord' => $this->prenda, 'pageClass' => EditPrenda::class])
            ->callTableAction('attach', data: ['recordId' => $zona->id, 'precio' => '3,00', 'coste' => '1.20'])
            ->assertHasNoTableActionErrors();

        $pivote = $this->prenda->zonas()->sole()->pivot;
        $this->assertSame([300, 120], [$pivote->precio, $pivote->coste]);
    }

    public function test_la_guia_de_tallas_se_escribe_en_cm_y_no_repite_talla(): void
    {
        $tabla = fn () => Livewire::test(MedidasRelationManager::class, ['ownerRecord' => $this->prenda, 'pageClass' => EditPrenda::class]);

        $tabla()->callTableAction('create', data: ['talla_id' => $this->tallas['M']->id, 'largo_mm' => 72, 'ancho_mm' => 52.5])->assertHasNoTableActionErrors();
        $tabla()->callTableAction('create', data: ['talla_id' => $this->tallas['M']->id, 'largo_mm' => 70])->assertHasTableActionErrors(['talla_id']);

        $medida = $this->prenda->medidas()->sole();
        $this->assertSame([720, 525, null], [$medida->largo_mm, $medida->ancho_mm, $medida->manga_mm]);
    }

    public function test_la_lista_muestra_el_precio_desde_y_permite_papelera_y_recuperar(): void
    {
        Variante::factory()->for($this->prenda)->create(['precio_base' => 400]);

        Livewire::test(ListPrendas::class)->assertSee('4,00 €')->assertSee('Camiseta Essential');

        Livewire::test(EditPrenda::class, ['record' => $this->prenda->getRouteKey()])->callAction('delete');
        $this->assertSoftDeleted($this->prenda);

        Livewire::test(EditPrenda::class, ['record' => $this->prenda->getRouteKey()])->callAction('restore');
        $this->assertNotSoftDeleted($this->prenda);
    }

    public function test_produccion_no_entra_en_productos(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Produccion))->get('/admin/productos')->assertForbidden();
    }
}
