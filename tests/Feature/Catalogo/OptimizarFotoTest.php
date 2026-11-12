<?php

namespace Tests\Feature\Catalogo;

use App\Dominio\Catalogo\OptimizarFoto;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Catalogo\Prendas\Pages\ListPrendas;
use App\Models\Ajuste;
use App\Models\Foto;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Livewire\Livewire;
use Tests\TestCase;

/** D210: las fotos del catálogo se guardan en WebP y al tamaño máximo de Ajustes → General. */
class OptimizarFotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Necesita Imagick.');
        }
        Storage::fake('public');
    }

    /** Un PNG con fondo transparente y una «prenda» con ruido en el centro (pesa como una foto real). */
    private function png(string $ruta, int $ancho, int $alto): void
    {
        $imagen = new Imagick;
        $imagen->newImage($ancho, $alto, new ImagickPixel('transparent'));
        $prenda = new Imagick;
        $prenda->newPseudoImage(intdiv($ancho, 2), intdiv($alto, 2), 'plasma:fractal');
        $imagen->compositeImage($prenda, Imagick::COMPOSITE_OVER, intdiv($ancho, 4), intdiv($alto, 4));
        $imagen->setImageFormat('png');
        Storage::disk('public')->put($ruta, $imagen->getImageBlob());
    }

    public function test_al_subir_una_foto_se_guarda_en_webp_reducida_y_borra_el_original(): void
    {
        Ajuste::guardar('catalogo.foto_max_px', 1000);
        $this->png('catalogo/1/grande.png', 1500, 1800);
        $antes = Storage::disk('public')->size('catalogo/1/grande.png');

        $foto = Foto::factory()->create(['ruta' => 'catalogo/1/grande.png']);

        $this->assertSame('catalogo/1/grande.webp', $foto->fresh()->ruta);
        Storage::disk('public')->assertMissing('catalogo/1/grande.png');
        [$ancho, $alto, $tipo] = getimagesize(Storage::disk('public')->path('catalogo/1/grande.webp'));
        $this->assertSame([833, 1000, IMAGETYPE_WEBP], [$ancho, $alto, $tipo]); // mantiene la proporción 5:6
        $this->assertLessThan($antes, Storage::disk('public')->size('catalogo/1/grande.webp'));
        $this->assertTrue((new Imagick(Storage::disk('public')->path('catalogo/1/grande.webp')))->getImageAlphaChannel()); // conserva la transparencia
    }

    public function test_el_tamano_maximo_se_configura_y_las_fotos_antiguas_se_optimizan_con_la_tarea(): void
    {
        Ajuste::guardar('catalogo.foto_max_px', 600);
        $this->png('catalogo/2/antigua.png', 750, 900);
        $foto = Foto::factory()->make(['ruta' => 'catalogo/2/antigua.png']);
        $foto->saveQuietly(); // como una foto subida antes de este cambio

        $this->artisan('catalogo:optimizar-fotos')->assertSuccessful();

        $this->assertSame('catalogo/2/antigua.webp', $foto->fresh()->ruta);
        $this->assertSame(600, getimagesize(Storage::disk('public')->path('catalogo/2/antigua.webp'))[1]);
        $this->assertFalse(OptimizarFoto::pendiente($foto->fresh()));

        // Ya optimizada: la tarea no la vuelve a tocar.
        $this->artisan('catalogo:optimizar-fotos')->expectsOutputToContain('ya estaban optimizadas')->assertSuccessful();
    }

    public function test_si_no_hay_archivo_no_falla(): void
    {
        $foto = Foto::factory()->create(['ruta' => 'catalogo/3/no-existe.png']);

        $this->assertSame('catalogo/3/no-existe.png', $foto->fresh()->ruta);
    }

    public function test_el_boton_del_catalogo_las_optimiza_en_el_momento(): void
    {
        $this->png('catalogo/4/antigua.png', 750, 900);
        $foto = Foto::factory()->make(['ruta' => 'catalogo/4/antigua.png']);
        $foto->saveQuietly(); // subida antes de optimizar al subir

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::Administrador));
        Livewire::test(ListPrendas::class)->callAction('optimizar_fotos')->assertNotified();

        $this->assertSame('catalogo/4/antigua.webp', $foto->fresh()->ruta);
        Storage::disk('public')->assertMissing('catalogo/4/antigua.png');
        Livewire::test(ListPrendas::class)->callAction('optimizar_fotos')->assertNotified('Todas las fotos ya estaban optimizadas');
    }
}
