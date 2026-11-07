<?php

use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Acceso\SincronizarRoles;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Crea los cuatro roles con sus permisos.
     *
     * Los usuarios creados antes de que existieran los roles (pasos 1.1 y
     * 1.2) eran todos del equipo: pasan a Superadministrador para que nadie
     * se quede sin acceso.
     */
    public function up(): void
    {
        (new SincronizarRoles)();

        if (! User::role(Rol::valores())->exists()) {
            User::query()->each(fn (User $usuario) => $usuario->assignRole(Rol::Superadministrador->value));
        }
    }

    public function down(): void
    {
        // Las tablas de roles se eliminan al deshacer su propia migración.
    }
};
