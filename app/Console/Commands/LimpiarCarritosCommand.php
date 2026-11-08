<?php

namespace App\Console\Commands;

use App\Dominio\Carrito\LimpiarCarritos;
use Illuminate\Console\Command;

/** Tarea diaria (paso 5.5): borra los carritos sin usar desde hace más de los días configurados. */
class LimpiarCarritosCommand extends Command
{
    protected $signature = 'carritos:limpiar';

    protected $description = 'Borra los carritos sin usar desde hace más de los días de Ajustes → General';

    public function handle(LimpiarCarritos $limpiar): int
    {
        $this->info('Carritos borrados: '.$limpiar().'.');

        return self::SUCCESS;
    }
}
