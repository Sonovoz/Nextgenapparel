<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * php artisan nga:esperar-bd
 *
 * Espera a que la base de datos acepte conexiones (arranque de Docker).
 */
class EsperarBaseDatos extends Command
{
    protected $signature = 'nga:esperar-bd {--segundos=120 : Tiempo máximo de espera}';

    protected $description = 'Espera a que la base de datos acepte conexiones';

    public function handle(): int
    {
        $limite = time() + (int) $this->option('segundos');

        while (true) {
            try {
                DB::connection()->getPdo();

                return self::SUCCESS;
            } catch (Throwable $error) {
                if (time() >= $limite) {
                    $this->components->error('La base de datos no responde: '.$error->getMessage());

                    return self::FAILURE;
                }

                DB::purge();
                sleep(2);
            }
        }
    }
}
