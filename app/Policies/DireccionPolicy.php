<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Direccion;
use App\Models\User;

/** Direcciones de los clientes en el backend (paso 5.4): las gestiona quien tiene Clientes. */
class DireccionPolicy
{
    private function puede(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Clientes->permiso());
    }

    public function viewAny(User $usuario): bool
    {
        return $this->puede($usuario);
    }

    public function view(User $usuario, Direccion $direccion): bool
    {
        return $this->puede($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->puede($usuario);
    }

    public function update(User $usuario, Direccion $direccion): bool
    {
        return $this->puede($usuario);
    }

    public function delete(User $usuario, Direccion $direccion): bool
    {
        return $this->puede($usuario);
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }
}
