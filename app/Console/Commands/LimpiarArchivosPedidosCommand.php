<?php

namespace App\Console\Commands;

use App\Dominio\Pedidos\LimpiarArchivosPedidos;
use Illuminate\Console\Command;

/** php artisan pedidos:limpiar-archivos — D230: borra los archivos de impresión de pedidos pasado su plazo. */
class LimpiarArchivosPedidosCommand extends Command
{
    protected $signature = 'pedidos:limpiar-archivos';

    protected $description = 'Borra los archivos de impresión de los pedidos enviados hace más de los días de Ajustes';

    public function handle(LimpiarArchivosPedidos $limpiar): int
    {
        $r = $limpiar();
        $this->info("Pedidos limpiados: {$r['pedidos']}. Archivos borrados: {$r['archivos']}.");

        return self::SUCCESS;
    }
}
