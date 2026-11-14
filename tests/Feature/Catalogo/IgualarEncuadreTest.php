<?php

namespace Tests\Feature\Catalogo;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\IgualarEncuadre;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Catalogo\Prendas\Pages\EditPrenda;
use App\Filament\Resources\Catalogo\Prendas\RelationManagers\FotosRelationManager;
use App\Models\Color;
use App\Models\Foto;
use App\Models\FotoZona;
use App\Models\Prenda;
use App\Models\ZonaImpresion;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Livewire\Livewire;
use Tests\TestCase;

/** D239: las maquetas de todos los colores, con la prenda al tamaño y en el sitio de la del blanco. */
class IgualarEncuadreTest extends TestCase
{
    use RefreshDatabase;

    private Prenda $prenda;

    private Color $blanco;

    private Color $negro;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Necesita Imagick.');
        }
        Storage::fake('public');
        $this->prenda = Prenda::factory()->create();
        $this->blanco = Color::factory()->create(['nombre' => ['es' => 'Blanco'], 'codigo_hex' => '#FFFFFF']);
        $this->negro = Color::factory()->create(['nombre' => ['es' => 'Negro'], 'codigo_hex' => '#111111']);
    }

    /** Una «prenda» (rectángulo) sobre fondo transparente o liso. */
    private function foto(Color $color, string $nombre, int $ancho, int $alto, array $prenda, string $fondo = 'transparent'): Foto
    {
        $imagen = new Imagick;
        $imagen->newImage($ancho, $alto, new ImagickPixel($fondo));
        [$x, $y, $w, $h] = $prenda;
        $dibujo = new ImagickDraw;
        $dibujo->setFillColor($color->codigo_hex === '#FFFFFF' ? '#E8E8E8' : '#222222');
        $dibujo->rectangle($x, $y, $x + $w - 1, $y + $h - 1);
        $imagen->drawImage($dibujo);
        $imagen->setImageFormat('png');
        Storage::disk('public')->put("catalogo/{$nombre}.png", $imagen->getImageBlob());

        return Foto::factory()->create(['prenda_id' => $this->prenda->id, 'color_id' => $color->id, 'cara' => Cara::Delantera, 'ruta' => "catalogo/{$nombre}.png"]);
    }

    /** @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int} ancho, alto y caja de la prenda */
    private function medir(Foto $foto): array
    {
        $imagen = new Imagick;
        $imagen->readImageBlob((string) Storage::disk('public')->get($foto->fresh()->ruta));

        return [$imagen->getImageWidth(), $imagen->getImageHeight(), ...(array) IgualarEncuadre::caja($imagen)];
    }

    private function assertMismaPrenda(array $esperado, array $real): void
    {
        foreach ($esperado as $i => $valor) {
            // Al escalar, los bordes se suavizan unos píxeles (más o menos según la versión de ImageMagick): margen del 1 %.
            $this->assertEqualsWithDelta($valor, $real[$i], 10, 'Medida '.$i.': '.json_encode($real));
        }
    }

    public function test_al_subir_otro_color_se_iguala_a_la_maqueta_del_blanco(): void
    {
        $blanca = $this->foto($this->blanco, 'blanca', 1000, 1200, [250, 200, 500, 700]);
        $negra = $this->foto($this->negro, 'negra', 800, 800, [100, 50, 400, 560]); // otro lienzo y la prenda más pequeña

        $this->assertSame([1000, 1200, 250, 200, 500, 700], $this->medir($blanca)); // la referencia no se toca
        $this->assertMismaPrenda([1000, 1200, 250, 200, 500, 700], $this->medir($negra));
        $this->assertTrue((new Imagick(Storage::disk('public')->path($negra->fresh()->ruta)))->getImageAlphaChannel()); // sigue transparente
    }

    public function test_el_boton_iguala_las_que_se_subieron_antes_que_el_blanco_y_les_copia_sus_zonas(): void
    {
        $negra = $this->foto($this->negro, 'negra', 900, 900, [300, 100, 300, 420], fondo: '#F4F4F4'); // fondo liso, no transparente
        $blanca = $this->foto($this->blanco, 'blanca', 1000, 1200, [250, 200, 500, 700]);
        $zona = ZonaImpresion::factory()->create();
        FotoZona::updateOrCreate(['foto_id' => $blanca->id, 'zona_impresion_id' => $zona->id], ['x' => 3500, 'y' => 2500, 'ancho' => 3000, 'alto' => 3000]);

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::Superadministrador));
        Livewire::test(FotosRelationManager::class, ['ownerRecord' => $this->prenda, 'pageClass' => EditPrenda::class])
            ->callTableAction('igualar')->assertNotified('1 foto igualada al blanco');

        $this->assertMismaPrenda([1000, 1200, 250, 200, 500, 700], $this->medir($negra));
        $this->assertSame([3500, 2500, 3000, 3000], array_values(FotoZona::where('foto_id', $negra->id)->sole()->only(['x', 'y', 'ancho', 'alto']))); // las zonas del blanco

        // Ya igualadas: no se rehacen.
        $this->assertSame(['igualadas' => 0, 'iguales' => 1, 'fallidas' => []], IgualarEncuadre::prenda($this->prenda));
    }

    public function test_si_no_se_distingue_la_prenda_del_fondo_no_se_toca_y_se_dice(): void
    {
        $this->foto($this->blanco, 'blanca', 1000, 1200, [250, 200, 500, 700]);
        $llena = $this->foto($this->negro, 'llena', 600, 600, [0, 0, 600, 600]); // la prenda ocupa toda la foto
        $ruta = $llena->fresh()->ruta;

        $r = IgualarEncuadre::prenda($this->prenda);

        $this->assertSame(0, $r['igualadas']);
        $this->assertStringContainsString('no se distingue la prenda del fondo', $r['fallidas'][0]);
        $this->assertSame($ruta, $llena->fresh()->ruta);
    }
}
