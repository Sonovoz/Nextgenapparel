<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\EntradaBlog;
use App\Models\User;

/** Blog (v2, D195): lo gestiona quien tiene la sección Marketing. */
class EntradaBlogPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Marketing->permiso());
    }

    public function view(User $usuario, EntradaBlog $entrada): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    public function update(User $usuario, EntradaBlog $entrada): bool
    {
        return $this->viewAny($usuario);
    }

    public function delete(User $usuario, EntradaBlog $entrada): bool
    {
        return $this->viewAny($usuario);
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }
}
