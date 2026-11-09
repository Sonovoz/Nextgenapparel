<?php

namespace App\Console\Commands;

use App\Dominio\Marketing\AvisarCarritos;
use Illuminate\Console\Command;

class AvisarCarritosCommand extends Command
{
    protected $signature = 'carritos:avisar';

    protected $description = 'Enviar los avisos de carritos abandonados (Marketing → Carritos abandonados)';

    public function handle(AvisarCarritos $avisar): int
    {
        $this->info('Avisos enviados: '.$avisar());

        return self::SUCCESS;
    }
}
