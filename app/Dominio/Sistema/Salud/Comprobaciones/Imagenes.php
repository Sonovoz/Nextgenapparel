<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Disenos\AnalizadorPng;
use App\Dominio\Sistema\Salud\Resultado;
use Imagick;

/**
 * Sin Imagick no se analizan los diseños de los clientes (fase 4) ni se generan las
 * maquetas (D98). No basta con que esté instalada: se analiza de verdad un PNG con
 * márgenes conocidos y se codifica a WebP, como en cada subida. Así se detecta una
 * ImageMagick que lea el alfa al revés o que no tenga WebP.
 */
class Imagenes implements Comprobacion
{
    /** PNG de 40 × 30 con un rectángulo opaco de 20 × 15 en (10, 5) y el resto transparente. */
    public const PRUEBA = 'salud/transparencia.png';

    public function clave(): string
    {
        return 'imagenes';
    }

    public function nombre(): string
    {
        return 'Procesado de imágenes';
    }

    public function comprobar(): array
    {
        if (! extension_loaded('imagick')) {
            return Resultado::fallo('Falta la extensión Imagick: no se pueden analizar los diseños de los clientes.');
        }
        if (Imagick::queryFormats('WEBP') === []) {
            return Resultado::fallo('ImageMagick no tiene WebP: fallarían todas las subidas de diseños.');
        }

        $webp = '';
        $analisis = (new AnalizadorPng)->analizar(resource_path(self::PRUEBA), ['max_mb' => 1, 'max_px' => 100, 'min_px' => 1],
            function (Imagick $imagen) use (&$webp): void {
                $imagen->setImageFormat('webp');
                $webp = $imagen->getImagesBlob();
            });

        $obtenido = [$analisis->utilX, $analisis->utilY, $analisis->utilAnchoPx, $analisis->utilAltoPx, $analisis->fondo];
        if ($obtenido !== [10, 5, 20, 15, 'transparente']) {
            return Resultado::fallo('El análisis de PNG da un resultado erróneo ('.json_encode($obtenido).'): revisa la versión de ImageMagick.');
        }
        if (! str_starts_with($webp, 'RIFF')) {
            return Resultado::fallo('ImageMagick no genera WebP.');
        }

        return Resultado::correcto(Imagick::getVersion()['versionString']);
    }
}
