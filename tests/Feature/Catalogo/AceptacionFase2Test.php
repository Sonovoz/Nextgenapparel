<?php

namespace Tests\Feature\Catalogo;

use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Catalogo\Pages\ManageFamilias;
use App\Filament\Resources\Catalogo\Prendas\Pages\CreatePrenda;
use App\Filament\Resources\Catalogo\Prendas\Pages\EditPrenda;
use App\Filament\Resources\Catalogo\Prendas\RelationManagers\FotosRelationManager;
use App\Filament\Resources\Catalogo\Prendas\RelationManagers\VariantesRelationManager;
use App\Filament\Resources\Catalogo\Prendas\RelationManagers\ZonasRelationManager;
use App\Models\Color;
use App\Models\Familia;
use App\Models\Foto;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\ZonaImpresion;
use Database\Seeders\CatalogoInicialSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Criterio de aceptación de la fase 2 (ficha § 15): «Se crea una prenda
 * variable completa desde el backend y se ve en el frontend en es/en con su
 * sitemap y datos estructurados». De principio a fin, como lo haría el equipo.
 */
class AceptacionFase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_una_prenda_creada_desde_el_backend_se_ve_en_la_tienda_en_los_dos_idiomas(): void
    {
        Storage::fake(Foto::DISCO);
        $this->seed(CatalogoInicialSeeder::class); // colores, tallas y zonas de partida
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        // 1. Familia
        Livewire::test(ManageFamilias::class)
            ->callAction('create', [
                'nombre' => ['es' => 'Camisetas premium', 'en' => 'Premium tees'],
                'slug' => ['es' => 'camisetas-premium', 'en' => 'premium-tees'],
                'plazo_base_dias' => 4,
            ])
            ->assertHasNoFormErrors();

        // 2. Prenda
        $familia = Familia::where('slug->es', 'camisetas-premium')->sole();
        Livewire::test(CreatePrenda::class)
            ->fillForm([
                'familia_id' => $familia->id,
                'nombre' => ['es' => 'Camiseta Heavy', 'en' => 'Heavy T-shirt'],
                'slug' => ['es' => 'camiseta-heavy', 'en' => 'heavy-t-shirt'],
                'descripcion' => ['es' => 'Algodón grueso de 220 g.', 'en' => 'Heavy 220 g cotton.'],
                'gramaje' => 220,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $prenda = Prenda::where('slug->es', 'camiseta-heavy')->sole();
        $relacion = fn (string $clase) => Livewire::test($clase, ['ownerRecord' => $prenda, 'pageClass' => EditPrenda::class]);

        // 3. Variaciones: 2 colores × 2 tallas a 5,00 €; la XL negra, a 6,00 €.
        $negro = Color::where('nombre->es', 'Negro')->sole();
        $blanco = Color::where('nombre->es', 'Blanco')->sole();
        $relacion(VariantesRelationManager::class)
            ->callTableAction('generar', data: [
                'colores' => [$negro->id, $blanco->id],
                'tallas' => [Talla::where('nombre', 'M')->value('id'), Talla::where('nombre', 'XL')->value('id')],
                'precio_base' => '5,00', 'coste' => '2,50', 'peso_gramos' => 220, 'stock' => 50,
            ])
            ->assertHasNoTableActionErrors();
        $xlNegra = $prenda->variantes()->where('color_id', $negro->id)->where('talla_id', Talla::where('nombre', 'XL')->value('id'))->sole();
        $relacion(VariantesRelationManager::class)
            ->callAction(TestAction::make('edit')->table($xlNegra), ['precio_base' => '6,00'])
            ->assertHasNoFormErrors();

        // 4. Zona frontal a 3,00 €
        $frontal = ZonaImpresion::where('clave', 'frontal')->sole();
        $relacion(ZonasRelationManager::class)
            ->callTableAction('attach', data: ['recordId' => $frontal->id, 'precio' => '3,00', 'coste' => '1,00'])
            ->assertHasNoTableActionErrors();

        // 5. Maqueta negra delantera (las zonas se colocan solas)
        $relacion(FotosRelationManager::class)
            ->callTableAction('create', data: ['tipo' => 'maqueta', 'color_id' => $negro->id, 'cara' => 'delantera', 'ruta' => UploadedFile::fake()->image('heavy.png', 1000, 1200)])
            ->assertHasNoTableActionErrors();

        Auth::logout();

        // ── Tienda en español ──
        $es = $this->get('/es/camisetas-premium/camiseta-heavy')->assertOk()
            ->assertSee('Camiseta Heavy')
            ->assertSee('6,05 €') // 5,00 + 21 %
            ->assertSee('Hasta 28 × 38 cm')
            ->getContent();
        $this->get('/es/camisetas-premium')->assertOk()->assertSee('Camiseta Heavy');

        preg_match_all('#<script type="application/ld\+json">(.+?)</script>#s', $es, $bloques);
        $producto = json_decode($bloques[1][0], true);
        $this->assertSame('Product', $producto['@type']);
        $this->assertSame(['6.05', '7.26', 4], [$producto['offers']['lowPrice'], $producto['offers']['highPrice'], $producto['offers']['offerCount']]);
        $this->assertStringContainsString('catalogo/'.$prenda->id.'/', $producto['image'][0]);
        $this->assertStringContainsString('<meta property="og:image" content="'.$producto['image'][0].'">', $es);

        preg_match("/data-colores='([^']+)'/", $es, $visor);
        $negroVisor = collect(json_decode(html_entity_decode($visor[1]), true))->firstWhere('nombre', 'Negro');
        $this->assertSame('Frontal', $negroVisor['caras']['delantera']['zonas'][0]['nombre']);

        // ── Tienda en inglés ──
        $this->get('/en/premium-tees/heavy-t-shirt')->assertOk()
            ->assertSee('Heavy T-shirt')
            ->assertSee('Heavy 220 g cotton.')
            ->assertSee('<link rel="canonical" href="'.url('/en/premium-tees/heavy-t-shirt').'">', false)
            ->assertSee('hreflang="es" href="'.url('/es/camisetas-premium/camiseta-heavy').'"', false);

        // ── Sitemaps ──
        $this->get('/sitemap-es.xml')->assertOk()
            ->assertSee('<loc>'.url('/es/camisetas-premium/camiseta-heavy').'</loc>', false)
            ->assertSee('hreflang="en" href="'.url('/en/premium-tees/heavy-t-shirt').'"', false);
        $this->get('/sitemap-en.xml')->assertOk()
            ->assertSee('<loc>'.url('/en/premium-tees/heavy-t-shirt').'</loc>', false)
            ->assertSee('<loc>'.url('/en/premium-tees').'</loc>', false);
    }
}
