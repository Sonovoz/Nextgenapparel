<?php

namespace Tests\Feature\Disenos;

use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Disenos\NivelCalidad;
use App\Models\Diseno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\PngDePrueba;
use Tests\TestCase;

/** Subida de diseños desde la tienda (paso 4.3). */
class SubidaDisenoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Falta la extensión Imagick (en Herd: PHP → Extensions).');
        }
        Storage::fake(GuardarDiseno::DISCO);
    }

    public function test_guarda_el_original_intacto_y_una_miniatura_privados_y_devuelve_lo_que_se_imprime(): void
    {
        $png = PngDePrueba::crear(1200, 900, 'transparent', [100, 50, 800, 600]);

        $respuesta = $this->subir($png, 'mi-logo.png')->assertCreated()
            ->assertJson(['nombre' => 'mi-logo.png', 'ancho_px' => 800, 'alto_px' => 600, 'fondo' => 'transparente', 'avisos' => []]);

        $diseno = Diseno::sole();
        $this->assertSame($respuesta->json('uuid'), $diseno->uuid);
        $this->assertSame(hash('sha256', session('disenos.propietario')), $diseno->propietario);
        $this->assertNotNull($diseno->derechos_declarados_en);
        $this->assertSame(file_get_contents($png), Storage::disk('disenos')->get($diseno->ruta));
        $this->assertSame('image/webp', Storage::disk('disenos')->mimeType($diseno->miniatura));
        $this->assertArrayNotHasKey('ruta', $diseno->toArray());
    }

    public function test_sin_declarar_los_derechos_no_se_guarda_nada(): void
    {
        $this->subir(PngDePrueba::crear(600, 600, 'white'), derechos: false)
            ->assertUnprocessable()->assertJsonValidationErrors(['derechos' => 'confirma que tienes los derechos']);

        $this->assertNada();
    }

    public function test_un_archivo_que_no_es_png_se_rechaza_con_su_motivo_y_no_se_guarda(): void
    {
        $this->subir(PngDePrueba::archivo('GIF89a no es un PNG'), 'logo.png')
            ->assertUnprocessable()->assertJson(['motivo' => 'no_png']);

        $this->assertNada();
    }

    public function test_el_mismo_archivo_dos_veces_no_se_duplica(): void
    {
        $png = PngDePrueba::crear(600, 600, 'white');

        $uuid = $this->subir($png)->assertCreated()->json('uuid');
        $this->subir($png)->assertOk()->assertJson(['uuid' => $uuid]);

        $this->assertSame(1, Diseno::count());
        $this->assertCount(2, Storage::disk('disenos')->allFiles());
    }

    public function test_avisa_del_fondo_blanco_y_de_un_perfil_de_color_que_no_es_srgb(): void
    {
        $png = PngDePrueba::conFragmento(PngDePrueba::crear(600, 600, 'white'), 'iCCP', "Adobe RGB (1998)\0\0".gzcompress('perfil'));

        $avisos = $this->subir($png)->assertCreated()->json('avisos');

        $this->assertSame([__('disenos.fondo_blanco'), __('disenos.aviso_perfil', ['perfil' => 'Adobe RGB (1998)'])], $avisos);
    }

    public function test_la_miniatura_solo_la_ve_su_dueno(): void
    {
        $url = $this->subir(PngDePrueba::crear(600, 600, 'white'))->json('miniatura');

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff');

        // Otra sesión (otro visitante) y una sesión sin diseños: 404, sin desvelar que existe.
        $this->flushSession();
        $this->get($url)->assertNotFound();
        $this->subir(PngDePrueba::crear(700, 700, 'white'))->assertCreated();
        $this->get($url)->assertNotFound();
        $this->get(route('disenos.miniatura', ['idioma' => 'es', 'diseno' => '00000000-0000-0000-0000-000000000000']))->assertNotFound();
    }

    public function test_si_falla_la_base_de_datos_no_quedan_archivos_huerfanos(): void
    {
        Diseno::creating(fn () => throw new RuntimeException('Base de datos caída'));

        $this->withoutExceptionHandling();
        try {
            $this->subir(PngDePrueba::crear(600, 600, 'white'));
            $this->fail('Se esperaba el error de la base de datos.');
        } catch (RuntimeException) {
            $this->assertSame([], Storage::disk('disenos')->allFiles());
        }
    }

    public function test_limita_las_subidas_por_hora(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/es/disenos')->assertUnprocessable();
        }

        $this->postJson('/es/disenos')->assertTooManyRequests();
    }

    public static function nombres(): array
    {
        return [
            'con ruta relativa' => ['../../x.png', 'x.png'],
            'con ruta de Windows' => ['C:\\fakepath\\logo.png', 'logo.png'],
            'con caracteres de control' => ["lo\x00go\n.png", 'logo.png'],
            'vacío' => ['', 'diseno.png'],
        ];
    }

    #[DataProvider('nombres')]
    public function test_el_nombre_que_se_muestra_no_lleva_rutas_ni_caracteres_de_control(string $original, string $esperado): void
    {
        $this->assertSame($esperado, GuardarDiseno::nombre($original));
    }

    public function test_la_calidad_del_pedido_se_mide_sobre_lo_que_se_imprime(): void
    {
        // 3000 px útiles dentro de un lienzo de 4000: a 25,4 cm son 300 ppp, no 400.
        $this->subir(PngDePrueba::crear(4000, 3000, 'transparent', [0, 0, 3000, 3000]))->assertCreated();

        $this->assertSame(['ppp' => 300, 'nivel' => NivelCalidad::Correcta], Diseno::sole()->calidad(254, 254));
        $this->assertSame(NivelCalidad::Justa, Diseno::sole()->calidad(400, 400)['nivel']);
    }

    private function subir(string $ruta, string $nombre = 'diseno.png', bool $derechos = true): TestResponse
    {
        return $this->postJson('/es/disenos', array_filter([
            'archivo' => new UploadedFile($ruta, $nombre, 'image/png', null, true),
            'derechos' => $derechos ? '1' : null,
        ]));
    }

    private function assertNada(): void
    {
        $this->assertSame(0, Diseno::count());
        $this->assertSame([], Storage::disk('disenos')->allFiles());
    }
}
