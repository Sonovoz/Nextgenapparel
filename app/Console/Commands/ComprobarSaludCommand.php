<?php

namespace App\Console\Commands;

use App\Dominio\Sistema\Salud\ComprobarSalud;
use App\Dominio\Sistema\Salud\EstadoSalud;
use Illuminate\Console\Command;

/**
 * php artisan nga:comprobar-salud
 *
 * Se ejecuta solo cada 5 minutos (tareas programadas). Muestra el estado de
 * cada comprobación y avisa por correo si algo falla.
 */
class ComprobarSaludCommand extends Command
{
    protected $signature = 'nga:comprobar-salud {--sin-avisos : No envía correos, solo muestra el estado}';

    protected $description = 'Comprueba la salud del sistema y avisa por correo si algo falla';

    public function handle(ComprobarSalud $comprobar): int
    {
        $resultados = $comprobar(avisar: ! $this->option('sin-avisos'));

        $this->table(['Comprobación', 'Estado', 'Detalle'], array_map(fn ($r) => [
            $r->nombre, $r->estado->getLabel(), $r->mensaje,
        ], $resultados));

        $fallos = count(array_filter($resultados, fn ($r) => $r->estado === EstadoSalud::Fallo));

        return $fallos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
