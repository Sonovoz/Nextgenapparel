<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\GrupoCliente;
use App\Models\User;

/** Grupos y tarifas (v2, D192): quien tiene la sección Clientes. No se borran (se desactivan). */
class GrupoClientePolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Clientes->permiso());
    }

    public function view(User $usuario, GrupoCliente $grupo): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    public function update(User $usuario, GrupoCliente $grupo): bool
    {
        return $this->viewAny($usuario);
    }

    public function delete(User $usuario, GrupoCliente $grupo): bool
    {
        return false;
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }
}
