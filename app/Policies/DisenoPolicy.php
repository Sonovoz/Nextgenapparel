<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Diseno;
use App\Models\User;

/**
 * Biblioteca de diseños (fase 4): la ve y la gestiona quien tiene la sección
 * Clientes. Los diseños los crean los clientes desde la tienda, nunca el backend,
 * y no se editan: el original es el que subió el cliente.
 */
class DisenoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Clientes->permiso());
    }

    public function view(User $usuario, Diseno $diseno): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return false;
    }

    public function update(User $usuario, Diseno $diseno): bool
    {
        return false;
    }

    /** Borrar un diseño (por ejemplo, a petición del cliente, RGPD). */
    public function delete(User $usuario, Diseno $diseno): bool
    {
        return $this->viewAny($usuario);
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }
}
