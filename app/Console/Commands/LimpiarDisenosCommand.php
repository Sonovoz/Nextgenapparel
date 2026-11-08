<?php

namespace App\Console\Commands;

use App\Dominio\Disenos\LimpiarDisenos;
use Illuminate\Console\Command;

/**
 * php artisan disenos:limpiar
 *
 * Se ejecuta sola cada noche (tareas programadas): borra los diseños de visitantes
 * sin cuenta que superan los días de Ajustes y los archivos huérfanos.
 */
class LimpiarDisenosCommand extends Command
{
    protected $signature = 'disenos:limpiar';

    protected $description = 'Borra los diseños sin pedido de visitantes sin cuenta y los archivos huérfanos';

    public function handle(LimpiarDisenos $limpiar): int
    {
        $resultado = $limpiar();
        $this->info("Diseños borrados: {$resultado['disenos']}. Archivos huérfanos borrados: {$resultado['huerfanos']}.");

        return self::SUCCESS;
    }
}
