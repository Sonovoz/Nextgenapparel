<?php

namespace App\Dominio\Disenos;

use App\Models\Ajuste;
use Imagick;
use ImagickException;

/**
 * Análisis de un diseño en el servidor (fase 4, ficha § 7). Es el que vale
 * para el pedido; el visor repite los mismos criterios para avisar al momento:
 *
 * - Solo PNG de verdad (firma del archivo, no la extensión ni el tipo que dice el navegador).
 * - Límites antes de descomprimir: bytes y píxeles (Ajustes → General).
 * - Lo que se imprime es el diseño sin sus márgenes transparentes: alfa > 8 de 255 (D114).
 * - Fondo: transparente si más del 0,2 % de los píxeles tiene alfa < 250; si no,
 *   fondo blanco cuando más del 90 % del borde es blanco (> 235 en R, G y B).
 */
final class AnalizadorPng
{
    public const FIRMA = "\x89PNG\r\n\x1a\n";

    private const ALFA_VISIBLE = 8;

    private const ALFA_OPACO = 250;

    private const FRACCION_TRANSPARENTE = 0.002;

    private const BLANCO = 235;

    private const FRACCION_BORDE_BLANCO = 0.9;

    public function analizar(string $ruta): AnalisisPng
    {
        $limites = self::limites();

        $bytes = @filesize($ruta);
        if ($bytes === false || $bytes === 0) {
            throw new DisenoNoValido('ilegible');
        }
        if ($bytes > $limites['max_mb'] * 1024 * 1024) {
            throw new DisenoNoValido('pesado', ['mb' => $limites['max_mb']]);
        }
        if (@file_get_contents($ruta, false, null, 0, 8) !== self::FIRMA) {
            throw new DisenoNoValido('no_png');
        }

        // Las medidas se leen de la cabecera, sin descomprimir: un PNG enorme no llega a Imagick.
        $medidas = @getimagesize($ruta);
        if ($medidas === false || $medidas[2] !== IMAGETYPE_PNG) {
            throw new DisenoNoValido('ilegible');
        }
        [$ancho, $alto] = $medidas;
        if (max($ancho, $alto) > $limites['max_px']) {
            throw new DisenoNoValido('enorme', ['px' => $limites['max_px']]);
        }

        $imagen = new Imagick;
        try {
            // «png:» obliga a leerlo como PNG; «[0]» se queda con el primer fotograma (APNG).
            $imagen->readImage('png:'.$ruta.'[0]');
        } catch (ImagickException) {
            throw new DisenoNoValido('ilegible');
        }

        try {
            [$util, $fondo] = $imagen->getImageAlphaChannel()
                ? $this->conTransparencia($imagen)
                : [[0, 0, $ancho, $alto], null];

            $fondo ??= $this->fondoOpaco($imagen, $ancho, $alto);

            if (min($util[2], $util[3]) < $limites['min_px']) {
                throw new DisenoNoValido('pequeno', ['px' => $limites['min_px'], 'ancho' => $util[2], 'alto' => $util[3]]);
            }

            return new AnalisisPng(
                anchoPx: $ancho,
                altoPx: $alto,
                utilX: $util[0],
                utilY: $util[1],
                utilAnchoPx: $util[2],
                utilAltoPx: $util[3],
                fondo: $fondo,
                perfilColor: $this->perfilColor($ruta),
                profundidadBits: (int) $imagen->getImageDepth(),
            );
        } catch (ImagickException) {
            // Sin memoria o disco para procesarla (límites de ImageMagick): demasiado grande.
            throw new DisenoNoValido('procesar');
        } finally {
            $imagen->clear();
        }
    }

    /**
     * Peso máximo que de verdad puede llegar: el de Ajustes, sin pasar del límite de subida
     * de PHP (en Herd, 2 MB si no se cambia; en Docker, 50 MB).
     */
    public static function maxMbEfectivo(): int
    {
        $php = min(self::bytesIni((string) ini_get('upload_max_filesize')), self::bytesIni((string) ini_get('post_max_size')));
        $ajuste = self::limites()['max_mb'];

        return $php > 0 ? max(1, min($ajuste, intdiv($php, 1024 * 1024))) : $ajuste;
    }

    /** «2M», «512K», «1G» o bytes; 0 = sin límite. */
    public static function bytesIni(string $valor): int
    {
        $valor = trim($valor);
        $numero = (int) $valor;

        return match (strtoupper(substr($valor, -1))) {
            'G' => $numero * 1024 ** 3,
            'M' => $numero * 1024 ** 2,
            'K' => $numero * 1024,
            default => $numero,
        };
    }

    /** @return array{max_mb: int, max_px: int, min_px: int} */
    public static function limites(): array
    {
        return [
            'max_mb' => (int) Ajuste::obtener('disenos.max_mb'),
            'max_px' => (int) Ajuste::obtener('disenos.max_px'),
            'min_px' => (int) Ajuste::obtener('disenos.min_px'),
        ];
    }

    /**
     * Rectángulo visible (alfa > 8) y tipo de fondo de un PNG con canal alfa.
     *
     * @return array{0: array{0: int, 1: int, 2: int, 3: int}, 1: ?string}
     */
    private function conTransparencia(Imagick $imagen): array
    {
        $q = Imagick::getQuantum();
        $medioNivel = 0.5 * $q / 255;

        // Máscara de alfa en gris: opaco = blanco. (En ImageMagick 6 el canal separado sale invertido.)
        $mascara = clone $imagen;
        $mascara->separateImageChannel(Imagick::CHANNEL_ALPHA);
        $mascara->setImageAlphaChannel(Imagick::ALPHACHANNEL_DEACTIVATE);
        $mascara->negateImage(false);

        $visible = clone $mascara;
        $visible->thresholdImage(self::ALFA_VISIBLE / 255 * $q + $medioNivel);
        if ($visible->getImageChannelMean(Imagick::CHANNEL_GRAY)['mean'] <= 0) {
            throw new DisenoNoValido('vacio');
        }
        // Un borde negro garantiza que el recorte quite solo lo transparente.
        $visible->borderImage('black', 1, 1);
        $visible->trimImage(0);
        $pagina = $visible->getImagePage();
        $util = [$pagina['x'] - 1, $pagina['y'] - 1, $visible->getImageWidth(), $visible->getImageHeight()];

        $visible->clear();
        // La máscara ya no hace falta: se umbraliza sobre ella misma, sin otra copia.
        $mascara->thresholdImage(self::ALFA_OPACO / 255 * $q - $medioNivel);
        $transparente = 1 - $mascara->getImageChannelMean(Imagick::CHANNEL_GRAY)['mean'] / $q;
        $mascara->clear();

        // Casi sin transparencia: se trata como una imagen opaca (su fondo se mira en el borde).
        return [$util, $transparente > self::FRACCION_TRANSPARENTE ? 'transparente' : null];
    }

    private function fondoOpaco(Imagick $imagen, int $ancho, int $alto): string
    {
        $borde = array_merge(
            $imagen->exportImagePixels(0, 0, $ancho, 1, 'RGB', Imagick::PIXEL_CHAR),
            $imagen->exportImagePixels(0, $alto - 1, $ancho, 1, 'RGB', Imagick::PIXEL_CHAR),
            $imagen->exportImagePixels(0, 0, 1, $alto, 'RGB', Imagick::PIXEL_CHAR),
            $imagen->exportImagePixels($ancho - 1, 0, 1, $alto, 'RGB', Imagick::PIXEL_CHAR),
        );

        $blancos = 0;
        $total = intdiv(count($borde), 3);
        for ($i = 0; $i < count($borde); $i += 3) {
            if ($borde[$i] > self::BLANCO && $borde[$i + 1] > self::BLANCO && $borde[$i + 2] > self::BLANCO) {
                $blancos++;
            }
        }

        return $blancos / $total > self::FRACCION_BORDE_BLANCO ? 'fondo_blanco' : 'fondo';
    }

    /** Nombre del perfil ICC incrustado (fragmento iCCP), «sRGB», «Gris» o null (RGB sin perfil). */
    private function perfilColor(string $ruta): ?string
    {
        $archivo = @fopen($ruta, 'rb');
        if ($archivo !== false) {
            try {
                fseek($archivo, 8);
                // Los fragmentos de color van antes de los datos de imagen (IDAT).
                while (($cabecera = fread($archivo, 8)) !== false && strlen($cabecera) === 8) {
                    ['largo' => $largo, 'tipo' => $tipo] = unpack('Nlargo/a4tipo', $cabecera);
                    if ($tipo === 'IDAT' || $tipo === 'IEND') {
                        break;
                    }
                    if ($tipo === 'sRGB') {
                        return 'sRGB';
                    }
                    if ($tipo === 'iCCP') {
                        // Nombre del perfil: hasta 79 bytes Latin-1 terminados en cero.
                        $nombre = strstr((string) fread($archivo, min($largo, 80)), "\0", true);

                        return $nombre !== false && $nombre !== '' ? mb_convert_encoding($nombre, 'UTF-8', 'ISO-8859-1') : 'ICC';
                    }
                    fseek($archivo, $largo + 4, SEEK_CUR); // datos + CRC
                }
            } finally {
                fclose($archivo);
            }
        }

        // Tipo de color de la cabecera IHDR (byte 25): 0 y 4 son escala de grises.
        $tipoColor = ord((string) @file_get_contents($ruta, false, null, 25, 1));

        return in_array($tipoColor, [0, 4], true) ? 'Gris' : null;
    }
}
