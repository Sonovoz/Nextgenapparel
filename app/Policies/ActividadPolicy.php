<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Registro de actividad: solo lectura, para quienes tienen Herramientas.
 * Nadie puede modificar ni borrar lo registrado.
 */
class ActividadPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Herramientas->permiso());
    }

    public function view(User $usuario, Activity $actividad): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return false;
    }

    public function update(User $usuario, Activity $actividad): bool
    {
        return false;
    }

    public function delete(User $usuario, Activity $actividad): bool
    {
        return false;
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }
}
