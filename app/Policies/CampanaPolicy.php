<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Campana;
use App\Models\User;

/** Campañas (v2, D190): las crea, edita (mientras no se envían) y envía quien tiene la sección Marketing. */
class CampanaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Marketing->permiso());
    }

    public function view(User $usuario, Campana $campana): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    public function update(User $usuario, Campana $campana): bool
    {
        return $this->viewAny($usuario) && $campana->enviada_en === null;
    }

    public function delete(User $usuario, Campana $campana): bool
    {
        return false;
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }
}
