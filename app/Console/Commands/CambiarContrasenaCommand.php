<?php

namespace App\Console\Commands;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

/**
 * Pone una contraseña nueva a una cuenta del backend desde el servidor, si se ha olvidado.
 * La pide dos veces sin mostrarla en pantalla ni dejarla en el historial de la terminal.
 * Anula el «Recordarme» de la cuenta y queda en la actividad (sin la contraseña).
 *
 * php artisan usuarios:cambiar-contrasena ana@ejemplo.es
 */
class CambiarContrasenaCommand extends Command
{
    protected $signature = 'usuarios:cambiar-contrasena {correo : Correo de la cuenta}';

    protected $description = 'Pone una contraseña nueva a una cuenta del backend (si se ha olvidado)';

    public function handle(): int
    {
        $usuario = User::where('email', mb_strtolower(trim((string) $this->argument('correo'))))->first();
        if ($usuario === null) {
            $this->components->error('No hay ninguna cuenta con ese correo.');

            return self::FAILURE;
        }

        $nueva = password(label: 'Contraseña nueva (mínimo 12 caracteres)', required: true);
        $repetida = password(label: 'Repite la contraseña nueva', required: true);

        if ($nueva !== $repetida) {
            $this->components->error('Las dos contraseñas no coinciden. No se ha cambiado nada.');

            return self::FAILURE;
        }

        $validador = Validator::make(['password' => $nueva], ['password' => ['required', Password::min(12)]], [], ['password' => 'contraseña']);
        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $usuario->forceFill([
            'password' => $nueva,
            'remember_token' => Str::random(60),
        ])->save();
        Actividad::apuntar('usuarios', 'Contraseña cambiada desde el servidor', $usuario);

        $this->components->info("Contraseña cambiada para {$usuario->email}. Entra en ".url('/admin').' con la nueva.');

        return self::SUCCESS;
    }
}
