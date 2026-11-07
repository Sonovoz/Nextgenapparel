<?php

namespace App\Console\Commands;

use App\Dominio\Catalogo\ZonasPorDefecto;
use Illuminate\Console\Command;

class RecolocarZonasCommand extends Command
{
    protected $signature = 'catalogo:recolocar-zonas';

    protected $description = 'Mueve a la nueva posición de partida las zonas de las maquetas que no se han ajustado a mano';

    public function handle(): int
    {
        $movidas = ZonasPorDefecto::recolocarAnteriores();
        $this->info("Zonas recolocadas: {$movidas}. Las ajustadas a mano no se han tocado.");

        return self::SUCCESS;
    }
}
