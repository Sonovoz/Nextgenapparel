<?php

namespace Tests\Support;

use Imagick;
use ImagickDraw;
use ImagickPixel;

/**
 * PNG sintéticos para los tests de diseños (fase 4). Sustituyen a los PNG reales
 * de clientes hasta que estén disponibles.
 */
final class PngDePrueba
{
    /** Lienzo con fondo (`transparent`, `white`, `#123456`…) y, opcionalmente, un rectángulo opaco [x, y, ancho, alto]. */
    public static function crear(int $ancho, int $alto, string $fondo = 'transparent', ?array $rectangulo = null, string $formato = 'png32', int $profundidad = 8): string
    {
        $imagen = new Imagick;
        $imagen->newImage($ancho, $alto, new ImagickPixel($fondo), 'png');
        if ($rectangulo !== null) {
            [$x, $y, $w, $h] = $rectangulo;
            $dibujo = new ImagickDraw;
            $dibujo->setFillColor('#C8102E');
            $dibujo->rectangle($x, $y, $x + $w - 1, $y + $h - 1);
            $imagen->drawImage($dibujo);
        }
        $imagen->setImageDepth($profundidad);

        return self::guardar($imagen, $formato);
    }

    /** Lienzo transparente con un único píxel de alfa exacto (0–255) en (x, y). */
    public static function conPixel(int $ancho, int $alto, int $x, int $y, int $alfa): string
    {
        $imagen = new Imagick;
        $imagen->newImage($ancho, $alto, new ImagickPixel('transparent'), 'png');
        $imagen->importImagePixels($x, $y, 1, 1, 'RGBA', Imagick::PIXEL_CHAR, [255, 0, 0, $alfa]);

        return self::guardar($imagen, 'png32');
    }

    /** Ruido aleatorio opaco: un PNG que apenas se comprime (para el límite de peso). */
    public static function ruido(int $ancho, int $alto): string
    {
        $imagen = new Imagick;
        $imagen->newPseudoImage($ancho, $alto, 'plasma:fractal');
        $imagen->addNoiseImage(Imagick::NOISE_RANDOM);

        return self::guardar($imagen, 'png24');
    }

    /** Inserta un fragmento PNG (p. ej. iCCP) justo después de la cabecera IHDR. */
    public static function conFragmento(string $ruta, string $tipo, string $datos): string
    {
        $png = file_get_contents($ruta);
        $finIhdr = 8 + 8 + 13 + 4;
        $fragmento = pack('N', strlen($datos)).$tipo.$datos.pack('N', crc32($tipo.$datos));
        file_put_contents($ruta, substr($png, 0, $finIhdr).$fragmento.substr($png, $finIhdr));

        return $ruta;
    }

    public static function archivo(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'png').'.png';
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    /** Un PNG que no declara su resolución (sin fragmento pHYs), para D206. */
    public static function sinPpp(int $ancho, int $alto): string
    {
        $imagen = new Imagick;
        $imagen->newImage($ancho, $alto, new ImagickPixel('white'), 'png');

        return self::guardar($imagen, 'png32', conPpp: false);
    }

    private static function guardar(Imagick $imagen, string $formato, bool $conPpp = true): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'png').'.png';
        $imagen->stripImage();
        if ($conPpp) {
            // Como un PNG exportado bien: declara su resolución (D206).
            $imagen->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
            $imagen->setImageResolution(300, 300);
            $imagen->setOption('png:include-chunk', 'pHYs'); // stripImage() lo quitaría
        }
        $imagen->writeImage($formato.':'.$ruta);

        return $ruta;
    }
}
