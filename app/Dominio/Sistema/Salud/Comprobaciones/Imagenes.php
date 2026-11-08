<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Sistema\Salud\Resultado;
use Imagick;

/** Sin Imagick no se analizan los diseños de los clientes (fase 4) ni se generan las maquetas (D98). */
class Imagenes implements Comprobacion
{
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

        if (Imagick::queryFormats('PNG') === []) {
            return Resultado::fallo('ImageMagick no puede leer PNG.');
        }

        return Resultado::correcto(Imagick::getVersion()['versionString']);
    }
}
