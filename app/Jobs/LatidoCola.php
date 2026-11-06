<?php

namespace App\Jobs;

use App\Dominio\Sistema\Salud\Latido;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Trabajo mínimo que las tareas programadas envían a la cola cada minuto.
 * Si la cola lo procesa, se registra el latido de las colas.
 */
class LatidoCola implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        Latido::registrar(Latido::COLAS);
    }
}
