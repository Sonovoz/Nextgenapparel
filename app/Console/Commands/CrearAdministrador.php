<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Da de alta un usuario del backend desde la terminal.
 *
 * php artisan nga:crear-administrador
 * php artisan nga:crear-administrador --nombre="Ana" --email=ana@ejemplo.es --password=...
 */
class CrearAdministrador extends Command
{
    protected $signature = 'nga:crear-administrador
                            {--nombre= : Nombre completo}
                            {--email= : Correo electrónico con el que entrará}
                            {--password= : Contraseña (mínimo 12 caracteres)}';

    protected $description = 'Crea un usuario administrador del backend';

    public function handle(): int
    {
        $datos = [
            'nombre' => $this->option('nombre') ?? text(label: 'Nombre completo', required: true),
            'email' => $this->option('email') ?? text(label: 'Correo electrónico', required: true),
            'password' => $this->option('password') ?? password(label: 'Contraseña (mínimo 12 caracteres)', required: true),
        ];

        $validador = Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(12)],
        ], [], [
            'nombre' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $datos['nombre'],
            'email' => $datos['email'],
            'password' => $datos['password'],
        ]);

        $this->components->info('Administrador creado. Entra en '.url('/admin')." con {$datos['email']}.");

        return self::SUCCESS;
    }
}
