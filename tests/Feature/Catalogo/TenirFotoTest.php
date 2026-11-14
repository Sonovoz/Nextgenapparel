<?php

namespace Tests\Feature\Catalogo;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\TenirFoto;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Catalogo\Prendas\Pages\EditPrenda;
use App\Filament\Resources\Catalogo\Prendas\RelationManagers\FotosRelationManager;
use App\Models\Color;
use App\Models\Foto;
use App\Models\FotoZona;
use App\Models\Prenda;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Livewire\Livewire;
use Tests\TestCase;

/** D251: maquetas de otros colores tiñendo la del blanco. */
class TenirFotoTest extends TestCase
{
    use RefreshDatabase;

    private Prenda $prenda;

    private Color $blanco;

    private Color $azul;

    private Color $negro;

    private Foto $base;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Necesita Imagick.');
        }
        Storage::fake('public');
        $this->prenda = Prenda::factory()->create();
        $this->blanco = Color::factory()->create(['nombre' => ['es' => 'Blanco'], 'codigo_hex' => '#FFFFFF']);
        $this->azul = Color::factory()->create(['nombre' => ['es' => 'Azul marino'], 'codigo_hex' => '#1F2A44']);
        $this->negro = Color::factory()->create(['nombre' => ['es' => 'Negro'], 'codigo_hex' => '#111111']);
        foreach ([$this->blanco, $this->azul, $this->negro] as $color) {
            Variante::factory()->create(['prenda_id' => $this->prenda->id, 'color_id' => $color->id]);
        }
        $this->base = $this->foto('blanca', 'transparent');
        $zona = ZonaImpresion::factory()->create();
        FotoZona::create(['foto_id' => $this->base->id, 'zona_impresion_id' => $zona->id, 'x' => 3500, 'y' => 2500, 'ancho' => 3000, 'alto' => 3000]);
    }

    /** Camiseta blanca de 400 × 500 px con un pliegue más oscuro, sobre fondo transparente (o blanco). */
    private function foto(string $nombre, string $fondo): Foto
    {
        $imagen = new Imagick;
        $imagen->newImage(400, 500, new ImagickPixel($fondo));
        $tela = new ImagickDraw;
        $tela->setFillColor('#EDEDED');
        $tela->rectangle(100, 80, 299, 419);
        $imagen->drawImage($tela);
        $pliegue = new ImagickDraw;
        $pliegue->setFillColor('#BDBDBD');
        $pliegue->rectangle(180, 80, 199, 419);
        $imagen->drawImage($pliegue);
        $imagen->setImageFormat('png');
        Storage::disk('public')->put("catalogo/{$nombre}.png", $imagen->getImageBlob());

        return Foto::factory()->create(['prenda_id' => $this->prenda->id, 'color_id' => $this->blanco->id, 'cara' => Cara::Delantera, 'ruta' => "catalogo/{$nombre}.png"]);
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} rgba 0–1 */
    private function pixel(Foto $foto, int $x, int $y): array
    {
        $imagen = new Imagick;
        $imagen->readImageBlob((string) Storage::disk('public')->get($foto->fresh()->ruta));
        $c = $imagen->getImagePixelColor($x, $y);

        return [$c->getColorValue(Imagick::COLOR_RED), $c->getColorValue(Imagick::COLOR_GREEN), $c->getColorValue(Imagick::COLOR_BLUE), $c->getColorValue(Imagick::COLOR_ALPHA)];
    }

    public function test_tine_la_blanca_con_el_color_de_la_ficha_conservando_pliegues_fondo_y_zonas(): void
    {
        $r = TenirFoto::colores($this->prenda, [$this->azul->id]);

        $this->assertSame([1, 0, ['trasera']], [$r['creadas'], $r['rehechas'], $r['sin_base']]);
        $azul = Foto::where('color_id', $this->azul->id)->sole();
        $this->assertSame($this->base->id, $azul->tenida_desde_id);
        [$r1, $g1, $b1] = $this->pixel($azul, 130, 250);   // tela lisa: el azul marino de la ficha
        $this->assertEqualsWithDelta(0x1F / 255, $r1, 0.08);
        $this->assertEqualsWithDelta(0x44 / 255, $b1, 0.08);
        $this->assertLessThan($b1, $this->pixel($azul, 190, 250)[2]); // el pliegue, más oscuro
        $this->assertLessThan(0.1, $this->pixel($azul, 10, 10)[3]);   // el fondo sigue transparente
        $this->assertSame([3500, 2500, 3000, 3000], array_values(FotoZona::where('foto_id', $azul->id)->sole()->only(['x', 'y', 'ancho', 'alto'])));
    }

    public function test_nunca_pisa_una_foto_real_y_rehace_las_tenidas(): void
    {
        $real = Foto::factory()->create(['prenda_id' => $this->prenda->id, 'color_id' => $this->negro->id, 'cara' => Cara::Delantera, 'ruta' => 'catalogo/negro-real.webp']);
        TenirFoto::colores($this->prenda, [$this->azul->id]);

        $r = TenirFoto::colores($this->prenda, [$this->azul->id, $this->negro->id]);

        $this->assertSame([0, 1, ['Negro (delantera)']], [$r['creadas'], $r['rehechas'], $r['con_foto_real']]);
        $this->assertSame('catalogo/negro-real.webp', $real->fresh()->ruta);
        $this->assertNull($real->fresh()->tenida_desde_id);
    }

    public function test_sin_fondo_transparente_no_tine_y_lo_dice(): void
    {
        Foto::query()->delete();
        $this->foto('blanca-opaca', 'white');

        $this->assertSame(['delantera'], TenirFoto::colores($this->prenda, [$this->azul->id])['sin_transparencia']);
        $this->assertSame(0, Foto::where('color_id', $this->azul->id)->count());
    }

    public function test_el_boton_del_backend_crea_los_colores(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::Superadministrador));

        Livewire::test(FotosRelationManager::class, ['ownerRecord' => $this->prenda, 'pageClass' => EditPrenda::class])
            ->callTableAction('tenir', data: ['colores' => [$this->azul->id, $this->negro->id]])
            ->assertNotified();

        $this->assertSame(2, Foto::whereNotNull('tenida_desde_id')->count());
    }
}
