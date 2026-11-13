<?php

namespace Tests\Feature\Disenos;

use App\Dominio\Disenos\AnalizadorPng;
use App\Dominio\Disenos\DisenoNoValido;
use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Salud\Comprobaciones\Imagenes;
use App\Dominio\Sistema\Salud\EstadoSalud;
use App\Filament\Pages\AjustesGenerales;
use App\Models\Ajuste;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Imagick;
use ImagickPixel;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PngDePrueba;
use Tests\TestCase;

/** Análisis del PNG en el servidor (fase 4): lo que vale para el pedido. */
class AnalizadorPngTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Falta la extensión Imagick (en Herd: PHP → Extensions).');
        }
    }

    public function test_mide_lo_que_se_imprime_sin_los_margenes_transparentes(): void
    {
        $analisis = (new AnalizadorPng)->analizar(PngDePrueba::crear(1000, 800, 'transparent', [100, 200, 400, 300]));

        $this->assertSame([1000, 800], [$analisis->anchoPx, $analisis->altoPx]);
        $this->assertSame([100, 200, 400, 300], [$analisis->utilX, $analisis->utilY, $analisis->utilAnchoPx, $analisis->utilAltoPx]);
        $this->assertSame('transparente', $analisis->fondo);
        $this->assertSame(8, $analisis->profundidadBits);
    }

    public function test_un_pixel_con_alfa_8_no_cuenta_y_con_alfa_9_si_como_en_el_visor(): void
    {
        // Diseño de 400 × 400 en la esquina y un píxel suelto lejos de él.
        $base = PngDePrueba::crear(1000, 1000, 'transparent', [0, 0, 400, 400]);
        $analizar = function (int $alfa) use ($base): array {
            $imagen = new Imagick($base);
            $imagen->importImagePixels(900, 950, 1, 1, 'RGBA', Imagick::PIXEL_CHAR, [0, 0, 255, $alfa]);
            $ruta = $base.$alfa.'.png';
            $imagen->writeImage('png32:'.$ruta);
            $a = (new AnalizadorPng)->analizar($ruta);

            return [$a->utilAnchoPx, $a->utilAltoPx];
        };

        $this->assertSame([400, 400], $analizar(8));
        $this->assertSame([901, 951], $analizar(9));
    }

    public static function fondos(): array
    {
        return [
            'blanco opaco' => ['white', 'png24', 'fondo_blanco'],
            'de color opaco' => ['#1F3BFF', 'png24', 'fondo'],
            'con canal alfa pero sin transparencia' => ['white', 'png32', 'fondo_blanco'],
        ];
    }

    #[DataProvider('fondos')]
    public function test_detecta_el_fondo_como_el_visor(string $color, string $formato, string $esperado): void
    {
        $analisis = (new AnalizadorPng)->analizar(PngDePrueba::crear(600, 600, $color, [100, 100, 400, 400], $formato));

        $this->assertSame($esperado, $analisis->fondo);
        $this->assertSame([0, 0, 600, 600], [$analisis->utilX, $analisis->utilY, $analisis->utilAnchoPx, $analisis->utilAltoPx]);
    }

    public function test_paleta_con_transparencia_y_gris_con_alfa(): void
    {
        $paleta = PngDePrueba::crear(800, 600, 'transparent', [100, 50, 400, 300], 'png8');
        $this->assertSame([100, 50, 400, 300, 'transparente'], $this->util($paleta));

        $gris = new Imagick;
        $gris->newImage(800, 600, new ImagickPixel('transparent'), 'png');
        $dibujo = new \ImagickDraw;
        $dibujo->setFillColor('gray40');
        $dibujo->rectangle(100, 50, 499, 349);
        $gris->drawImage($dibujo);
        $gris->setOption('png:color-type', '4');
        $ruta = PngDePrueba::archivo('');
        $gris->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
        $gris->setImageResolution(300, 300); // como un PNG bien exportado (D206)
        $gris->writeImage('png:'.$ruta);
        $this->assertSame(4, ord(file_get_contents($ruta, false, null, 25, 1)));
        $this->assertSame([100, 50, 400, 300, 'transparente'], $this->util($ruta));
    }

    public function test_un_margen_transparente_fino_no_se_confunde_con_el_fondo(): void
    {
        // 2000 × 2000 blanco con 1 px transparente alrededor: menos del 0,2 % transparente,
        // así que es una imagen opaca, y su borde es el blanco de dentro, no el margen.
        $ruta = PngDePrueba::crear(2000, 2000, 'transparent', null);
        $imagen = new Imagick($ruta);
        $dibujo = new \ImagickDraw;
        $dibujo->setFillColor('white');
        $dibujo->rectangle(1, 1, 1998, 1998);
        $imagen->drawImage($dibujo);
        $imagen->writeImage('png32:'.$ruta);

        $this->assertSame([1, 1, 1998, 1998, 'fondo_blanco'], $this->util($ruta));
    }

    /** @return array{0: int, 1: int, 2: int, 3: int, 4: string} */
    private function util(string $ruta): array
    {
        $a = (new AnalizadorPng)->analizar($ruta);

        return [$a->utilX, $a->utilY, $a->utilAnchoPx, $a->utilAltoPx, $a->fondo];
    }

    public static function rechazos(): array
    {
        return [
            'JPEG con extensión .png' => [fn () => self::jpeg(), 'no_png'],
            'texto con extensión .png' => [fn () => PngDePrueba::archivo('<?php echo 1;'), 'no_png'],
            'PNG cortado' => [fn () => PngDePrueba::archivo(AnalizadorPng::FIRMA.str_repeat("\0", 40)), 'ilegible'],
            'archivo vacío' => [fn () => PngDePrueba::archivo(''), 'ilegible'],
            'totalmente transparente' => [fn () => PngDePrueba::crear(800, 800), 'vacio'],
            'casi invisible (alfa 8)' => [fn () => PngDePrueba::conPixel(800, 800, 10, 10, 8), 'vacio'],
            'más pequeño que el mínimo sin márgenes' => [fn () => PngDePrueba::crear(1000, 1000, 'transparent', [0, 0, 299, 800]), 'pequeno'],
        ];
    }

    #[DataProvider('rechazos')]
    public function test_rechaza_lo_que_no_se_puede_imprimir(callable $archivo, string $motivo): void
    {
        $this->assertRechazado($archivo(), $motivo);
    }

    public function test_los_limites_de_peso_y_pixeles_salen_de_los_ajustes(): void
    {
        Ajuste::guardar('disenos.max_px', 500);
        $this->assertRechazado(PngDePrueba::crear(501, 400, 'white'), 'enorme', ['px' => 500]);
        $this->assertSame(500, (new AnalizadorPng)->analizar(PngDePrueba::crear(500, 400, 'white'))->anchoPx);

        Ajuste::guardar('disenos.max_mb', 1);
        Ajuste::guardar('disenos.max_px', 12000);
        $pesado = PngDePrueba::ruido(1000, 1000);
        $this->assertGreaterThan(1024 * 1024, filesize($pesado));
        $this->assertRechazado($pesado, 'pesado', ['mb' => 1]);

        Ajuste::guardar('disenos.min_px', 100);
        $this->assertSame(150, (new AnalizadorPng)->analizar(PngDePrueba::crear(400, 400, 'transparent', [0, 0, 150, 120]))->utilAnchoPx);
    }

    public function test_los_limites_se_configuran_en_ajustes_generales_con_registro_de_actividad(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(AjustesGenerales::class)
            ->assertSchemaStateSet(['max_mb' => 30, 'max_px' => 8000, 'min_px' => 300, 'horas_sin_venta' => 72, 'dias_archivos' => 90])
            ->fillForm(['max_px' => 20000])
            ->callAction('guardar')
            ->assertHasFormErrors(['max_px']);

        Livewire::test(AjustesGenerales::class)
            ->fillForm(['max_mb' => 20, 'min_px' => 500])
            ->callAction('guardar')
            ->assertNotified('Ajustes guardados');

        $this->assertSame(['max_mb' => 20, 'max_px' => 8000, 'min_px' => 500, 'exigir_ppp' => 1], AnalizadorPng::limites());
        $this->assertDatabaseHas('activity_log', ['description' => 'Límites de los archivos de diseño cambiados']);
    }

    public static function valoresIni(): array
    {
        return [['2M', 2 * 1024 ** 2], ['512K', 512 * 1024], ['1G', 1024 ** 3], ['55m', 55 * 1024 ** 2], ['1048576', 1048576], ['0', 0]];
    }

    #[DataProvider('valoresIni')]
    public function test_lee_los_limites_de_subida_de_php(string $valor, int $bytes): void
    {
        $this->assertSame($bytes, AnalizadorPng::bytesIni($valor));
    }

    public function test_el_peso_maximo_efectivo_nunca_supera_el_de_php_ni_el_de_ajustes(): void
    {
        $php = intdiv(min(AnalizadorPng::bytesIni((string) ini_get('upload_max_filesize')), AnalizadorPng::bytesIni((string) ini_get('post_max_size'))), 1024 * 1024);

        Ajuste::guardar('disenos.max_mb', 1);
        $this->assertSame(1, AnalizadorPng::maxMbEfectivo());
        Ajuste::guardar('disenos.max_mb', 50);
        $this->assertSame(min(50, max(1, $php)), AnalizadorPng::maxMbEfectivo());
    }

    public function test_lee_el_perfil_de_color(): void
    {
        $base = fn () => PngDePrueba::crear(400, 400, 'white');

        $this->assertNull((new AnalizadorPng)->analizar($base())->perfilColor);
        $this->assertSame('sRGB', (new AnalizadorPng)->analizar(PngDePrueba::conFragmento($base(), 'sRGB', "\0"))->perfilColor);
        $this->assertSame('Adobe RGB (1998)', (new AnalizadorPng)->analizar(
            PngDePrueba::conFragmento($base(), 'iCCP', "Adobe RGB (1998)\0\0".gzcompress('perfil'))
        )->perfilColor);

        $gris = new Imagick;
        $gris->newImage(400, 400, new ImagickPixel('gray50'), 'png');
        $gris->setImageType(Imagick::IMGTYPE_GRAYSCALE);
        $ruta = PngDePrueba::archivo('');
        $gris->setOption('png:color-type', '0');
        $gris->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
        $gris->setImageResolution(300, 300); // como un PNG bien exportado (D206)
        $gris->writeImage('png:'.$ruta);
        $this->assertSame('Gris', (new AnalizadorPng)->analizar($ruta)->perfilColor);
    }

    public function test_la_salud_del_sistema_analiza_de_verdad_un_png_y_genera_webp(): void
    {
        [$estado, $mensaje] = (new Imagenes)->comprobar();

        $this->assertSame(EstadoSalud::Correcto, $estado, $mensaje);
    }

    public function test_no_pone_limite_de_tiempo_a_imagemagick_porque_cuenta_desde_que_arranca_el_proceso(): void
    {
        // Con un «time» de 60 s, un proceso de PHP-FPM que lleva más de un minuto
        // vivo rechazaba todas las imágenes (encontrado en la pasada de navegador).
        (new AnalizadorPng)->analizar(PngDePrueba::crear(400, 400, 'white'));

        $this->assertGreaterThan(24 * 3600, Imagick::getResourceLimit(Imagick::RESOURCETYPE_TIME));
        $this->assertEquals(128 * 1024 * 1024, Imagick::getResourceLimit(Imagick::RESOURCETYPE_MEMORY));
    }

    public function test_acepta_png_de_16_bits(): void
    {
        $this->assertSame(16, (new AnalizadorPng)->analizar(PngDePrueba::crear(400, 400, 'transparent', [0, 0, 400, 400], 'png64', 16))->profundidadBits);
    }

    private function assertRechazado(string $ruta, string $motivo, array $datos = []): void
    {
        try {
            (new AnalizadorPng)->analizar($ruta);
            $this->fail("Se esperaba el rechazo «{$motivo}».");
        } catch (DisenoNoValido $e) {
            $this->assertSame($motivo, $e->motivo);
            foreach ($datos as $clave => $valor) {
                $this->assertSame($valor, $e->datos[$clave]);
            }
            $this->assertStringNotContainsString('disenos.error_', $e->mensaje());
        }
    }

    private static function jpeg(): string
    {
        $imagen = new Imagick;
        $imagen->newImage(400, 400, new ImagickPixel('white'), 'jpeg');
        $ruta = PngDePrueba::archivo('');
        $imagen->writeImage('jpeg:'.$ruta);

        return $ruta;
    }
}
