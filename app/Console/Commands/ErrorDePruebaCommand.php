<?php

namespace App\Console\Commands;

use App\Dominio\Sistema\Errores\ErrorDePrueba;
use Illuminate\Console\Command;

/**
 * php artisan nga:error-prueba
 *
 * Registra un error crítico de prueba y envía la alerta, para comprobar en
 * cualquier entorno que el log de errores y el aviso por correo funcionan.
 */
class ErrorDePruebaCommand extends Command
{
    protected $signature = 'nga:error-prueba';

    protected $description = 'Registra un error crítico de prueba y envía la alerta por correo';

    public function handle(): int
    {
        report(new ErrorDePrueba('Error de prueba provocado desde la consola.'));

        $correo = config('errores.correo_alertas');

        $this->components->info('Error de prueba registrado en Herramientas → Log de errores.');
        $this->components->info(filled($correo)
            ? "Alerta enviada a {$correo} (en local, mira storage/logs/laravel.log)."
            : 'No hay correo de alertas: añade ALERTAS_EMAIL al .env.');

        return self::SUCCESS;
    }
}
