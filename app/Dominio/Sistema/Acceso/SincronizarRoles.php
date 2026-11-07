<?php

namespace App\Dominio\Sistema\Acceso;

use App\Filament\Navegacion\GrupoNavegacion;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Deja en la base de datos exactamente los roles y permisos definidos en
 * `Rol` y `GrupoNavegacion`. Se puede ejecutar tantas veces como haga falta.
 */
class SincronizarRoles
{
    public const GUARD = 'web';

    public function __invoke(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (GrupoNavegacion::cases() as $grupo) {
            Permission::findOrCreate($grupo->permiso(), self::GUARD);
        }

        foreach (Rol::cases() as $rol) {
            Role::findOrCreate($rol->value, self::GUARD)->syncPermissions(
                array_map(fn (GrupoNavegacion $grupo) => $grupo->permiso(), $rol->grupos()),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
