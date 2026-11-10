<?php

namespace Tests\Feature\Disenos;

use App\Dominio\Disenos\AnalisisPng;
use App\Dominio\Disenos\AnalizadorPng;
use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Disenos\NivelCalidad;
use App\Models\Ajuste;
use App\Models\Diseno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use ImagickException;
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
        $this->assertSame(file_get_contents($png), Storage::disk('disenos')->get($diseno->ruta));
        $this->assertSame('image/webp', Storage::disk('disenos')->mimeType($diseno->miniatura));
        // El JSON no lleva nada interno: ni rutas, ni el dueño, ni la huella del archivo.
        $this->assertEmpty(array_intersect(['ruta', 'miniatura_ruta', 'propietario', 'sha256', 'id'], array_keys($respuesta->json())));

        // La miniatura es lo que se imprime (800 × 600), reducido a 600 px como mucho.
        $mini = new \Imagick;
        $mini->readImageBlob(Storage::disk('disenos')->get($diseno->miniatura));
        $this->assertSame([600, 450], [$mini->getImageWidth(), $mini->getImageHeight()]);
    }

    /** D206: se anota la resolución que declara el PNG (pHYs); si no la declara, queda vacía. */
    public function test_anota_los_ppp_que_declara_el_png(): void
    {
        $this->subir(PngDePrueba::crear(600, 600, 'white'), 'con-ppp.png')->assertCreated()->assertJson(['ppp_archivo' => 300]);
        $this->assertSame(300, Diseno::sole()->ppp_archivo);

        // Sin resolución declarada no se admite (D206)…
        $sin = PngDePrueba::sinPpp(500, 500);
        $this->assertNull(AnalizadorPng::pppDeclarados($sin));
        $this->subir($sin, 'sin-ppp.png')->assertUnprocessable()->assertJson(['motivo' => 'sin_ppp']);
        $this->assertSame(1, Diseno::count());

        // …salvo que en Ajustes → General se deje de exigir.
        Ajuste::guardar('disenos.exigir_ppp', 0);
        $this->subir($sin, 'sin-ppp.png')->assertCreated()->assertJson(['ppp_archivo' => null]);
    }

    /** D202: la declaración de derechos no se pide al subir; va en las condiciones de venta del pedido. */
    public function test_se_sube_sin_casilla_de_derechos_y_la_declaracion_va_en_las_condiciones(): void
    {
        $this->subir(PngDePrueba::crear(600, 600, 'white'), derechos: false)->assertCreated();

        $this->assertNull(Diseno::sole()->derechos_declarados_en);
        $this->assertStringContainsString('declaro que tengo los derechos sobre las imágenes y diseños', __('pedidos.condiciones_acepto'));
        $this->assertStringContainsString('Tus diseños y sus derechos', (string) file_get_contents(resource_path('views/legal/es/condiciones-de-venta.blade.php')));
        $this->assertStringContainsString('Your designs and their rights', (string) file_get_contents(resource_path('views/legal/en/condiciones-de-venta.blade.php')));
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

    public function test_las_subidas_por_hora_salen_de_los_ajustes(): void
    {
        Ajuste::guardar('disenos.subidas_hora', 2);

        $this->postJson('/es/disenos')->assertUnprocessable();
        $this->postJson('/es/disenos')->assertUnprocessable();
        $this->postJson('/es/disenos')->assertTooManyRequests();
    }

    public function test_dos_subidas_simultaneas_del_mismo_archivo_dejan_un_solo_diseno(): void
    {
        $png = PngDePrueba::crear(600, 600, 'white');
        // La otra petición crea su fila justo entre la comprobación y la creación de esta.
        Diseno::creating(function (Diseno $diseno) {
            if (Diseno::count() === 0) {
                Diseno::withoutEvents(fn () => Diseno::create([...$diseno->getAttributes(), 'uuid' => 'aaaaaaaa-0000-0000-0000-000000000001']));
            }
        });

        $this->subir($png)->assertOk()->assertJson(['uuid' => 'aaaaaaaa-0000-0000-0000-000000000001']);

        $this->assertSame(1, Diseno::count());
        $this->assertSame([], Storage::disk('disenos')->allFiles()); // los archivos de la otra petición no se crearon en esta prueba
    }

    public function test_un_fallo_de_imagemagick_al_hacer_la_miniatura_es_un_aviso_y_no_deja_archivos(): void
    {
        $this->app->bind(AnalizadorPng::class, fn () => new class extends AnalizadorPng
        {
            public function analizar(string $ruta, ?array $limites = null, ?callable $conImagen = null): AnalisisPng
            {
                return parent::analizar($ruta, $limites, fn () => throw new ImagickException('sin WebP'));
            }
        });

        $this->subir(PngDePrueba::crear(600, 600, 'white'))->assertUnprocessable()->assertJson(['motivo' => 'procesar']);
        $this->assertNada();
    }

    public function test_con_todos_los_turnos_de_analisis_ocupados_pide_volver_a_intentarlo(): void
    {
        GuardarDiseno::$esperaSegundos = 0;
        $candados = array_map(fn ($t) => tap(Cache::lock("disenos:analisis:{$t}", 60))->get(), range(1, GuardarDiseno::ANALISIS_SIMULTANEOS));

        try {
            $this->subir(PngDePrueba::crear(600, 600, 'white'))->assertUnprocessable()->assertJson(['motivo' => 'ocupado']);
            $this->assertNada();
        } finally {
            array_walk($candados, fn ($c) => $c->release());
            GuardarDiseno::$esperaSegundos = 15;
        }

        $this->subir(PngDePrueba::crear(600, 600, 'white'))->assertCreated();
    }

    public function test_hay_un_tope_de_disenos_nuevos_por_visitante_y_dia(): void
    {
        Ajuste::guardar('disenos.max_dia', 1);

        $png = PngDePrueba::crear(600, 600, 'white');
        $this->subir($png)->assertCreated();
        $this->subir($png)->assertOk(); // el mismo archivo no cuenta como nuevo
        $this->subir(PngDePrueba::crear(700, 700, 'white'))->assertUnprocessable()->assertJson(['motivo' => 'limite_dia']);

        $this->travel(1)->day();
        $this->subir(PngDePrueba::crear(700, 700, 'white'))->assertCreated();
    }

    public function test_un_envio_mayor_que_el_limite_de_php_recibe_el_aviso_de_subida_en_su_idioma(): void
    {
        $respuesta = $this->call('POST', '/en/disenos', server: ['CONTENT_LENGTH' => 4 * 1024 ** 3, 'HTTP_ACCEPT' => 'application/json']);

        $respuesta->assertStatus(413)->assertJson(['motivo' => 'subida']);
        $this->assertStringContainsString('We did not receive the whole file', $respuesta->json('message'));
    }

    public static function nombres(): array
    {
        return [
            'con ruta relativa' => ['../../x.png', 'x.png'],
            'con ruta de Windows' => ['C:\\fakepath\\logo.png', 'logo.png'],
            'con caracteres de control' => ["lo\x00go\n.png", 'logo.png'],
            'con inversión de texto (U+202E)' => ["logo\u{202E}gnp.exe", 'logognp.exe'],
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
