<?php

namespace Tests\Feature\Catalogo;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\ZonasPorDefecto;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Catalogo\Prendas\Pages\EditPrenda;
use App\Filament\Resources\Catalogo\Prendas\RelationManagers\FotosRelationManager;
use App\Models\Color;
use App\Models\Foto;
use App\Models\Prenda;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Database\Seeders\EjemplosCatalogoSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FotosTest extends TestCase
{
    use RefreshDatabase;

    private Prenda $prenda;

    private Color $negro;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Foto::DISCO);
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        $this->prenda = Prenda::factory()->create();
        $this->negro = Color::factory()->create(['nombre' => ['es' => 'Negro']]);
        $this->prenda->zonas()->attach(ZonaImpresion::factory()->create(['clave' => 'frontal', 'cara' => Cara::Delantera]), ['precio' => 300]);
        $this->prenda->zonas()->attach(ZonaImpresion::factory()->create(['clave' => 'pecho', 'cara' => Cara::Delantera]), ['precio' => 180]);
        $this->prenda->zonas()->attach(ZonaImpresion::factory()->create(['clave' => 'espalda', 'cara' => Cara::Trasera]), ['precio' => 350]);
    }

    private function tabla()
    {
        return Livewire::test(FotosRelationManager::class, ['ownerRecord' => $this->prenda, 'pageClass' => EditPrenda::class]);
    }

    private function subir(array $datos)
    {
        return $this->tabla()->callTableAction('create', data: $datos + ['ruta' => UploadedFile::fake()->image('maqueta.png', 1000, 1200)]);
    }

    public function test_subir_una_maqueta_guarda_el_archivo_y_marca_las_zonas_de_esa_cara(): void
    {
        $this->subir(['tipo' => 'maqueta', 'color_id' => $this->negro->id, 'cara' => 'delantera'])->assertHasNoTableActionErrors();

        $foto = Foto::sole();
        Storage::disk(Foto::DISCO)->assertExists($foto->ruta);
        $this->assertStringStartsWith('catalogo/'.$this->prenda->id.'/', $foto->ruta);
        $this->assertEqualsCanonicalizing(['frontal', 'pecho'], $foto->zonas()->with('zona')->get()->pluck('zona.clave')->all());
        $this->assertSame(ZonasPorDefecto::POSICIONES['frontal'][2], $foto->zonas()->whereHas('zona', fn ($q) => $q->where('clave', 'frontal'))->value('ancho'));
    }

    public function test_la_maqueta_trasera_solo_marca_la_espalda(): void
    {
        $this->subir(['tipo' => 'maqueta', 'color_id' => $this->negro->id, 'cara' => 'trasera']);

        $this->assertSame(['espalda'], Foto::sole()->zonas()->with('zona')->get()->pluck('zona.clave')->all());
    }

    public function test_una_maqueta_exige_color_y_cara_y_no_se_repite(): void
    {
        $this->subir(['tipo' => 'maqueta', 'color_id' => null, 'cara' => null])->assertHasTableActionErrors(['color_id', 'cara']);

        $this->subir(['tipo' => 'maqueta', 'color_id' => $this->negro->id, 'cara' => 'delantera'])->assertHasNoTableActionErrors();
        $this->subir(['tipo' => 'maqueta', 'color_id' => $this->negro->id, 'cara' => 'delantera'])->assertHasTableActionErrors(['cara' => 'unique']);

        $this->assertSame(1, Foto::count());
    }

    public function test_una_foto_de_galeria_no_necesita_color_ni_cara_ni_marca_zonas(): void
    {
        $this->subir(['tipo' => 'modelo'])->assertHasNoTableActionErrors();

        $this->assertSame(0, Foto::sole()->zonas()->count());
    }

    public function test_solo_se_aceptan_imagenes(): void
    {
        $this->tabla()->callTableAction('create', data: ['tipo' => 'modelo', 'ruta' => UploadedFile::fake()->create('diseno.pdf', 100, 'application/pdf')])
            ->assertHasTableActionErrors(['ruta']);
    }

    public function test_ajustar_las_zonas_en_porcentaje(): void
    {
        $this->subir(['tipo' => 'maqueta', 'color_id' => $this->negro->id, 'cara' => 'delantera']);
        $foto = Foto::sole();
        $frontal = $foto->zonas()->whereHas('zona', fn ($q) => $q->where('clave', 'frontal'))->sole();
        $pecho = $foto->zonas()->whereHas('zona', fn ($q) => $q->where('clave', 'pecho'))->sole();

        $this->tabla()
            ->mountAction(TestAction::make('edit')->table($foto))
            ->fillForm(['zonas' => [
                "record-{$frontal->id}" => ['x' => 31.5, 'y' => 27, 'ancho' => 37, 'alto' => 40],
                "record-{$pecho->id}" => ['x' => 55, 'y' => 24, 'ancho' => 14, 'alto' => 13],
            ]])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame([3150, 2700, 3700, 4000], [$frontal->fresh()->x, $frontal->fresh()->y, $frontal->fresh()->ancho, $frontal->fresh()->alto]);
    }

    public function test_al_borrar_la_foto_se_borra_el_archivo(): void
    {
        $this->subir(['tipo' => 'modelo']);
        $foto = Foto::sole();

        $this->tabla()->callAction(TestAction::make('delete')->table($foto));

        Storage::disk(Foto::DISCO)->assertMissing($foto->ruta);
        $this->assertModelMissing($foto);
    }

    public function test_los_tres_ejemplos_se_crean_completos_y_sin_duplicar(): void
    {
        $this->seed(EjemplosCatalogoSeeder::class);
        $this->seed(EjemplosCatalogoSeeder::class);

        $ejemplos = Prenda::whereIn('slug->es', ['camiseta-essential', 'polo-classic', 'sudadera-urban'])->withCount(['variantes', 'zonas', 'medidas'])->get()->keyBy(fn ($p) => $p->getTranslation('slug', 'es'));

        $this->assertCount(3, $ejemplos);
        $this->assertSame([12, 10, 12], [$ejemplos['camiseta-essential']->variantes_count, $ejemplos['polo-classic']->variantes_count, $ejemplos['sudadera-urban']->variantes_count]);
        $this->assertSame(420, $ejemplos['camiseta-essential']->precioDesde());
        $this->assertSame(2, $ejemplos['polo-classic']->zonas_count);
        $this->assertSame('Essential T-shirt', $ejemplos['camiseta-essential']->getTranslation('nombre', 'en'));
        // El peso crece con la talla.
        $pesos = Variante::where('prenda_id', $ejemplos['sudadera-urban']->id)->join('tallas', 'tallas.id', '=', 'variantes.talla_id')->orderBy('tallas.orden')->pluck('peso_gramos')->unique()->values()->all();
        $this->assertSame([480, 510, 540, 570, 600, 630], $pesos);
    }

    public function test_produccion_no_puede_subir_fotos(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Produccion));

        $this->get(EditPrenda::getUrl(['record' => $this->prenda]))->assertForbidden();
    }
}
