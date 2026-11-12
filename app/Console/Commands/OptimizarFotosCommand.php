<?php

namespace App\Console\Commands;

use App\Dominio\Catalogo\OptimizarFoto;
use App\Models\Foto;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

/**
 * Optimiza las fotos del catálogo que aún no lo están (D210): las subidas antes de este cambio o
 * con otro máximo. Las nuevas se optimizan al subirlas; esta tarea programada recoge las que falten.
 */
class OptimizarFotosCommand extends Command
{
    protected $signature = 'catalogo:optimizar-fotos';

    protected $description = 'Pasa a WebP y al tamaño máximo las fotos del catálogo que aún no lo están';

    public function handle(): int
    {
        if (! extension_loaded('imagick')) {
            $this->components->warn('Falta Imagick: no se pueden optimizar las fotos.');

            return self::SUCCESS;
        }

        $fotos = 0;
        $ahorro = 0;
        Foto::query()->orderBy('id')->each(function (Foto $foto) use (&$fotos, &$ahorro): void {
            $bytes = rescue(fn () => OptimizarFoto::aplicar($foto));
            if ($bytes !== null) {
                $fotos++;
                $ahorro += $bytes;
            }
        });

        $this->components->info($fotos === 0 ? 'Todas las fotos ya estaban optimizadas.' : "{$fotos} fotos optimizadas: ".Number::fileSize($ahorro, 1).' menos.');

        return self::SUCCESS;
    }
}
