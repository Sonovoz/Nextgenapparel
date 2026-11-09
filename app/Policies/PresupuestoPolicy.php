<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Presupuesto;
use App\Models\User;

/** Presupuestos (D187): los ve quien tiene la sección Pedidos. Los crea el cliente desde su carrito; no se editan ni se borran. */
class PresupuestoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Pedidos->permiso());
    }

    public function view(User $usuario, Presupuesto $presupuesto): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return false;
    }

    public function update(User $usuario, Presupuesto $presupuesto): bool
    {
        return false;
    }

    public function delete(User $usuario, Presupuesto $presupuesto): bool
    {
        return false;
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }
}
