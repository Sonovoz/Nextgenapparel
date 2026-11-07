<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Caja;
use App\Models\ClaseImpuesto;
use App\Models\Cupon;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\ReglaMinimo;
use App\Models\ReglaPrecio;
use App\Models\User;
use App\Models\ZonaEnvio;
use Illuminate\Database\Eloquent\Model;

/**
 * Precios, envíos y marketing: cada modelo pertenece a una sección del menú
 * (Ajustes, Marketing o Productos) y lo gestiona quien tiene su permiso.
 */
class SeccionPolicy
{
    private const SECCIONES = [
        ClaseImpuesto::class => GrupoNavegacion::Ajustes,
        ZonaEnvio::class => GrupoNavegacion::Ajustes,
        Caja::class => GrupoNavegacion::Ajustes,
        ReglaPrecio::class => GrupoNavegacion::Marketing,
        ReglaMinimo::class => GrupoNavegacion::Marketing,
        Cupon::class => GrupoNavegacion::Marketing,
        Pack::class => GrupoNavegacion::Productos,
    ];

    private function puede(User $usuario, string $modelo): bool
    {
        return $usuario->can(self::SECCIONES[$modelo]->permiso());
    }

    public function viewAny(User $usuario, ...$argumentos): bool
    {
        return true; // la sección la decide cada recurso con canViewAny (abajo)
    }

    public function before(User $usuario, string $habilidad, mixed $modelo = null): ?bool
    {
        $clase = is_object($modelo) ? $modelo::class : $modelo;
        if ($clase === null || ! isset(self::SECCIONES[$clase])) {
            return null;
        }
        if (! $this->puede($usuario, $clase)) {
            return false;
        }

        return null;
    }

    public function view(User $usuario, Model $registro): bool
    {
        return true;
    }

    public function create(User $usuario): bool
    {
        return true;
    }

    public function update(User $usuario, Model $registro): bool
    {
        return true;
    }

    /** No se borra lo que ya se usa: se desactiva. */
    public function delete(User $usuario, Model $registro): bool
    {
        return match (true) {
            $registro instanceof ClaseImpuesto => ! $registro->por_defecto
                && ! Prenda::withTrashed()->where('clase_impuesto_id', $registro->id)->exists()
                && ! Pack::withTrashed()->where('clase_impuesto_id', $registro->id)->exists(),
            $registro instanceof Cupon => $registro->usos === 0,
            default => true,
        };
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }

    public function restore(User $usuario, Model $registro): bool
    {
        return true;
    }

    public function forceDelete(User $usuario, Model $registro): bool
    {
        return false;
    }
}
