<?php

namespace App\Console\Commands;

use App\Dominio\Marketing\Campanas;
use Illuminate\Console\Command;

class CampanasAutomaticasCommand extends Command
{
    protected $signature = 'campanas:automaticas';

    protected $description = 'Enviar los correos de nueva compra y de reactivación';

    public function handle(Campanas $campanas): int
    {
        $this->info('Nueva compra: '.$campanas->nuevaCompra().' · Reactivación: '.$campanas->reactivacion());

        return self::SUCCESS;
    }
}
