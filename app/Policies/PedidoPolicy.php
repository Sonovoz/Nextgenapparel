<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Pedido;
use App\Models\User;

/**
 * Backend → Pedidos (paso 6.3): quien tiene la sección Pedidos (superadministrador,
 * administrador y gestor de la tienda). Un pedido no se edita: se cambia su estado, se
 * anotan notas y se ajusta su fecha estimada. Se crean desde la tienda; no se borran.
 */
class PedidoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Pedidos->permiso());
    }

    public function view(User $usuario, Pedido $pedido): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return false;
    }

    public function update(User $usuario, Pedido $pedido): bool
    {
        return $this->viewAny($usuario);
    }

    public function delete(User $usuario, Pedido $pedido): bool
    {
        return false;
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }
}
