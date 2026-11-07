<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\User;

/**
 * Gestión de usuarios del backend: solo con acceso a «Usuarios y roles»
 * (en la práctica, el superadministrador).
 *
 * Los usuarios no se borran: se desactivan, para conservar su historial.
 */
class UserPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $this->gestionaUsuarios($usuario);
    }

    public function view(User $usuario, User $otro): bool
    {
        return $this->gestionaUsuarios($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->gestionaUsuarios($usuario);
    }

    public function update(User $usuario, User $otro): bool
    {
        return $this->gestionaUsuarios($usuario);
    }

    public function delete(User $usuario, User $otro): bool
    {
        return false;
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }

    public function restore(User $usuario, User $otro): bool
    {
        return false;
    }

    public function forceDelete(User $usuario, User $otro): bool
    {
        return false;
    }

    public function forceDeleteAny(User $usuario): bool
    {
        return false;
    }

    private function gestionaUsuarios(User $usuario): bool
    {
        return $usuario->activo
            && $usuario->getAllPermissions()->contains('name', GrupoNavegacion::UsuariosYRoles->permiso());
    }
}
