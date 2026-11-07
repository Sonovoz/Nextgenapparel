<?php

namespace App\Dominio\Seo;

use App\Models\Ajuste;

/**
 * ¿Pueden los buscadores indexar la tienda? Solo en producción y con el
 * interruptor de Ajustes → General encendido (D102). El entorno de pruebas
 * y el local nunca se indexan, aunque se copie la base de datos.
 */
final class Indexacion
{
    public static function permitida(): bool
    {
        return app()->environment('production')
            && (bool) rescue(fn () => Ajuste::obtener('seo.indexar'), false, report: false);
    }

    public static function metaRobots(): string
    {
        return self::permitida() ? 'index, follow, max-image-preview:large' : 'noindex, nofollow';
    }
}
