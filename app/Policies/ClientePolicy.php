<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Cliente;
use App\Models\User;

/**
 * Backend → Clientes (paso 5.4): quien tiene la sección Clientes (superadministrador,
 * administrador y gestor de la tienda; producción no). Los clientes se crean desde la
 * tienda, no desde el backend.
 */
class ClientePolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Clientes->permiso());
    }

    public function view(User $usuario, Cliente $cliente): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return false;
    }

    public function update(User $usuario, Cliente $cliente): bool
    {
        return $this->viewAny($usuario);
    }

    /** Borrar es «tramitar el borrado» (BorrarCliente), con su propia acción. */
    public function delete(User $usuario, Cliente $cliente): bool
    {
        return $this->viewAny($usuario);
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }
}
