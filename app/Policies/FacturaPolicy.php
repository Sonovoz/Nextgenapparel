<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Factura;
use App\Models\User;

/** Facturas (paso 6.5a): solo lectura para quien tiene la sección Pedidos. Nunca se crean, editan ni borran a mano. */
class FacturaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Pedidos->permiso());
    }

    public function view(User $usuario, Factura $factura): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return false;
    }

    public function update(User $usuario, Factura $factura): bool
    {
        return false;
    }

    public function delete(User $usuario, Factura $factura): bool
    {
        return false;
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }
}
