<?php

namespace App\Console\Commands;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Emergencia (D186): quita la verificación en dos pasos de una cuenta desde el servidor, si se pierden el
 * móvil y los códigos de recuperación. Al entrar tendrá que configurarla otra vez. Queda en la actividad.
 */
class QuitarDosPasosCommand extends Command
{
    protected $signature = 'usuarios:quitar-dos-pasos {correo : Correo de la cuenta}';

    protected $description = 'Quita la verificación en dos pasos de una cuenta (si se ha perdido el móvil)';

    public function handle(): int
    {
        $usuario = User::where('email', mb_strtolower((string) $this->argument('correo')))->first();
        if ($usuario === null) {
            $this->error('No hay ninguna cuenta con ese correo.');

            return self::FAILURE;
        }
        $usuario->saveAppAuthenticationSecret(null);
        $usuario->saveAppAuthenticationRecoveryCodes(null);
        Actividad::apuntar('usuarios', 'Verificación en dos pasos quitada desde el servidor', $usuario);
        $this->info("Verificación en dos pasos quitada a {$usuario->email}. Al entrar tendrá que configurarla de nuevo.");

        return self::SUCCESS;
    }
}
