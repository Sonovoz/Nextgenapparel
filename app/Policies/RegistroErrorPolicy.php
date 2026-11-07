<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\RegistroError;
use App\Models\User;

/**
 * Log de errores: lo ven quienes tienen acceso a Herramientas. Los errores
 * los crea el sistema; en el backend solo se marcan como resueltos.
 */
class RegistroErrorPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Herramientas->permiso());
    }

    public function view(User $usuario, RegistroError $registro): bool
    {
        return $this->viewAny($usuario);
    }

    public function update(User $usuario, RegistroError $registro): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return false;
    }

    public function delete(User $usuario, RegistroError $registro): bool
    {
        return false;
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }
}
