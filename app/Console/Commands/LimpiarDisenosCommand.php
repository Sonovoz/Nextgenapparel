<?php

namespace App\Console\Commands;

use App\Dominio\Disenos\LimpiarDisenos;
use Illuminate\Console\Command;

/**
 * php artisan disenos:limpiar
 *
 * Se ejecuta sola cada hora (tareas programadas): borra los diseños sin venta que superan
 * las horas de Ajustes (D230) y los archivos huérfanos.
 */
class LimpiarDisenosCommand extends Command
{
    protected $signature = 'disenos:limpiar';

    protected $description = 'Borra los diseños sin venta pasadas las horas de Ajustes y los archivos huérfanos';

    public function handle(LimpiarDisenos $limpiar): int
    {
        $resultado = $limpiar();
        $this->info("Diseños borrados: {$resultado['disenos']}. Archivos huérfanos borrados: {$resultado['huerfanos']}.");

        return self::SUCCESS;
    }
}
