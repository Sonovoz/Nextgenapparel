<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\RegistroCorreo;
use App\Models\User;

/** D235: registro de correos, solo lectura para quienes tienen Herramientas. */
class RegistroCorreoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Herramientas->permiso());
    }

    public function view(User $usuario, RegistroCorreo $registro): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return false;
    }

    public function update(User $usuario, RegistroCorreo $registro): bool
    {
        return false;
    }

    public function delete(User $usuario, RegistroCorreo $registro): bool
    {
        return false;
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }
}
