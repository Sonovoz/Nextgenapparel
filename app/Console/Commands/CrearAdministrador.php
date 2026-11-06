<?php

namespace App\Console\Commands;

use App\Dominio\Sistema\Acceso\Rol;
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
                            {--password= : Contraseña (mínimo 12 caracteres)}
                            {--rol=superadministrador : superadministrador, administrador, gestor-tienda o produccion}';

    protected $description = 'Crea un usuario del backend (por defecto, superadministrador)';

    public function handle(): int
    {
        $datos = [
            'nombre' => $this->option('nombre') ?? text(label: 'Nombre completo', required: true),
            'email' => $this->option('email') ?? text(label: 'Correo electrónico', required: true),
            'password' => $this->option('password') ?? password(label: 'Contraseña (mínimo 12 caracteres)', required: true),
            'rol' => $this->option('rol'),
        ];

        $validador = Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(12)],
            'rol' => ['required', 'in:'.implode(',', Rol::valores())],
        ], [], [
            'nombre' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'rol' => 'rol',
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $usuario = User::create([
            'name' => $datos['nombre'],
            'email' => $datos['email'],
            'password' => $datos['password'],
        ]);
        $usuario->assignRole($datos['rol']);

        $rol = Rol::from($datos['rol'])->getLabel();
        $this->components->info("Usuario creado con el rol {$rol}. Entra en ".url('/admin')." con {$datos['email']}.");

        return self::SUCCESS;
    }
}
