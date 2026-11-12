<?php

namespace Tests\Feature\Catalogo;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\TipoFoto;
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

    public function test_las_zonas_de_partida_guardan_la_proporcion_real_y_empiezan_bajo_el_cuello(): void
    {
        // Foto de 1000 × 1200 a ≈ 8,8 px/cm: ancho% = cm × 0,88 y alto% = cm × 0,733.
        foreach (['frontal' => [28, 38], 'pecho' => [9, 9], 'espalda' => [30, 40]] as $clave => [$anchoCm, $altoCm]) {
            [$x, $y, $ancho, $alto] = ZonasPorDefecto::POSICIONES[$clave];
            $this->assertEqualsWithDelta($anchoCm * 88, $ancho, 30, "Ancho de {$clave}");
            $this->assertEqualsWithDelta($altoCm * 73.3, $alto, 30, "Alto de {$clave}");
            $this->assertLessThanOrEqual(2300, $y, "{$clave} empieza demasiado abajo");
        }

        // El frontal y la espalda, centrados; el pecho, a la derecha de la foto (izquierda de quien la lleva).
        $this->assertEqualsWithDelta(5000, ZonasPorDefecto::POSICIONES['frontal'][0] + ZonasPorDefecto::POSICIONES['frontal'][2] / 2, 10);
        $this->assertEqualsWithDelta(5000, ZonasPorDefecto::POSICIONES['espalda'][0] + ZonasPorDefecto::POSICIONES['espalda'][2] / 2, 10);
        $this->assertGreaterThan(5000, ZonasPorDefecto::POSICIONES['pecho'][0]);
    }

    public function test_recolocar_mueve_solo_las_zonas_que_siguen_en_la_posicion_anterior(): void
    {
        $this->subir(['tipo' => 'maqueta', 'color_id' => $this->negro->id, 'cara' => 'delantera']);
        $foto = Foto::sole();
        $frontal = $foto->zonas()->whereHas('zona', fn ($q) => $q->where('clave', 'frontal'))->sole();
        $pecho = $foto->zonas()->whereHas('zona', fn ($q) => $q->where('clave', 'pecho'))->sole();

        [$x, $y, $ancho, $alto] = ZonasPorDefecto::ANTERIORES['frontal'];
        $frontal->update(compact('x', 'y', 'ancho', 'alto'));
        $pecho->update(['x' => 6000, 'y' => 2000, 'ancho' => 800, 'alto' => 700]); // ajustada a mano

        $this->artisan('catalogo:recolocar-zonas')->expectsOutputToContain('Zonas recolocadas: 1')->assertSuccessful();

        $this->assertSame(ZonasPorDefecto::POSICIONES['frontal'], [$frontal->fresh()->x, $frontal->fresh()->y, $frontal->fresh()->ancho, $frontal->fresh()->alto]);
        $this->assertSame([6000, 2000, 800, 700], [$pecho->fresh()->x, $pecho->fresh()->y, $pecho->fresh()->ancho, $pecho->fresh()->alto]);
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
                "record-{$pecho->id}" => ['x' => 55, 'y' => 24, 'ancho' => 12, 'alto' => 13.6],
            ]])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame([3150, 2700, 3700, 4000], [$frontal->fresh()->x, $frontal->fresh()->y, $frontal->fresh()->ancho, $frontal->fresh()->alto]);
    }

    public function test_una_zona_que_se_sale_de_la_foto_da_un_aviso_y_no_un_error(): void
    {
        $this->subir(['tipo' => 'maqueta', 'color_id' => $this->negro->id, 'cara' => 'delantera']);
        $foto = Foto::sole();
        $frontal = $foto->zonas()->whereHas('zona', fn ($q) => $q->where('clave', 'frontal'))->sole();

        $this->tabla()
            ->mountAction(TestAction::make('edit')->table($foto))
            ->fillForm(['zonas' => ["record-{$frontal->id}" => ['x' => 90, 'y' => 20, 'ancho' => 20, 'alto' => 30]]])
            ->callMountedAction()
            ->assertHasFormErrors(["zonas.record-{$frontal->id}.ancho"]);

        $this->assertNotSame(9000, $frontal->fresh()->x);
    }

    public function test_una_maqueta_tiene_que_ser_5_6(): void
    {
        $this->tabla()->callTableAction('create', data: ['tipo' => 'maqueta', 'color_id' => $this->negro->id, 'cara' => 'delantera',
            'ruta' => UploadedFile::fake()->image('maqueta.png', 1120, 1267)])->assertHasTableActionErrors(['ruta']);
        $this->assertSame(0, Foto::count());

        // Otras fotos (modelo, detalle) pueden tener cualquier proporción.
        $this->tabla()->callTableAction('create', data: ['tipo' => 'modelo', 'ruta' => UploadedFile::fake()->image('modelo.png', 1600, 900)])->assertHasNoTableActionErrors();
    }

    public function test_el_rectangulo_de_una_zona_tiene_que_tener_su_proporcion(): void
    {
        $this->subir(['tipo' => 'maqueta', 'color_id' => $this->negro->id, 'cara' => 'delantera']);
        $foto = Foto::sole();
        $frontal = $foto->zonas()->whereHas('zona', fn ($q) => $q->where('clave', 'frontal'))->sole();

        // Frontal de 28 × 38 cm dibujada al 40 % × 40 % de una foto 5:6: proporción 0,83 en lugar de 0,74.
        $this->tabla()
            ->mountAction(TestAction::make('edit')->table($foto))
            ->fillForm(['zonas' => ["record-{$frontal->id}" => ['x' => 30, 'y' => 20, 'ancho' => 40, 'alto' => 40]]])
            ->callMountedAction()
            ->assertHasFormErrors(["zonas.record-{$frontal->id}.alto"]);
    }

    public function test_la_vista_previa_muestra_el_diseno_de_ejemplo_con_los_valores_sin_guardar(): void
    {
        $this->subir(['tipo' => 'maqueta', 'color_id' => $this->negro->id, 'cara' => 'delantera']);
        $foto = Foto::sole();
        $frontal = $foto->zonas()->whereHas('zona', fn ($q) => $q->where('clave', 'frontal'))->sole();

        // El formulario acepta el ejemplo y los valores sin guardarlos.
        $this->tabla()
            ->mountAction(TestAction::make('edit')->table($foto))
            ->fillForm(['ejemplo' => 'vertical', 'zonas' => ["record-{$frontal->id}" => ['x' => 33.3, 'y' => 21, 'ancho' => 30, 'alto' => 30]]])
            ->assertHasNoFormErrors();
        $this->assertSame(ZonasPorDefecto::POSICIONES['frontal'][0], $frontal->fresh()->x);

        // La vista previa dibuja el ejemplo con esos valores.
        $html = view('filament.fotos.vista-previa', [
            'getRecord' => fn () => $foto,
            'zonasFormulario' => [['zona_impresion_id' => $frontal->zona_impresion_id, 'x' => 33.3, 'y' => 21, 'ancho' => 30, 'alto' => 30]],
            'ejemplo' => 'vertical',
            'getStatePath' => fn () => 'mountedActions.0.data.vista_previa',
        ])->render();

        // Editor visual (arrastrar y cambiar el tamaño): con esos valores y escribiendo en las casillas de «zonas».
        $this->assertStringContainsString('Coloca las zonas sobre la foto', $html);
        $this->assertStringContainsString('data-ejemplo="vertical"', $html);
        $this->assertStringContainsString('33.3', $html);
        $this->assertStringContainsString('mountedActions.0.data.zonas', $html);
        $this->assertStringContainsString('preserveAspectRatio="xMidYMin meet"', $html); // centrado y arriba, como la tienda
    }

    public function test_al_cambiar_la_imagen_se_borra_el_archivo_anterior(): void
    {
        $this->subir(['tipo' => 'modelo']);
        $foto = Foto::sole();
        $anterior = $foto->ruta;
        Storage::disk(Foto::DISCO)->put('catalogo/nueva.webp', 'x');

        $foto->update(['ruta' => 'catalogo/nueva.webp']);

        Storage::disk(Foto::DISCO)->assertMissing($anterior);
        Storage::disk(Foto::DISCO)->assertExists('catalogo/nueva.webp');
    }

    public function test_si_una_maqueta_pasa_a_foto_de_modelo_pierde_cara_y_zonas(): void
    {
        $this->subir(['tipo' => 'maqueta', 'color_id' => $this->negro->id, 'cara' => 'delantera']);
        $foto = Foto::sole();
        $this->assertGreaterThan(0, $foto->zonas()->count());

        $foto->update(['tipo' => TipoFoto::Modelo]);

        $this->assertNull($foto->fresh()->cara);
        $this->assertSame(0, $foto->zonas()->count());
    }

    public function test_un_color_con_fotos_no_se_puede_borrar(): void
    {
        $this->subir(['tipo' => 'maqueta', 'color_id' => $this->negro->id, 'cara' => 'delantera']);

        $this->assertFalse(auth()->user()->can('delete', $this->negro));
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
