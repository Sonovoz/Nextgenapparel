<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Incidencia;
use App\Models\User;

/** Incidencias (paso 6.6b): las ve y las resuelve quien tiene la sección Pedidos. Las abre el cliente; no se editan ni se borran. */
class IncidenciaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Pedidos->permiso());
    }

    public function view(User $usuario, Incidencia $incidencia): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return false;
    }

    public function update(User $usuario, Incidencia $incidencia): bool
    {
        return false;
    }

    public function delete(User $usuario, Incidencia $incidencia): bool
    {
        return false;
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }

    /** Resolverla una vez (D178): reimpresión, reembolso parcial con su rectificativa o rechazo. */
    public function resolver(User $usuario, Incidencia $incidencia): bool
    {
        return $this->viewAny($usuario) && $incidencia->abierta();
    }
}
