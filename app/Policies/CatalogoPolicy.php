<?php

namespace App\Policies;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Color;
use App\Models\Familia;
use App\Models\Foto;
use App\Models\Proveedor;
use App\Models\Talla;
use App\Models\User;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo: lo gestionan los roles con acceso a Productos. No se puede
 * borrar lo que está en uso (se desactiva en su lugar).
 */
class CatalogoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(GrupoNavegacion::Productos->permiso());
    }

    public function view(User $usuario, Model $registro): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    public function update(User $usuario, Model $registro): bool
    {
        return $this->viewAny($usuario);
    }

    public function delete(User $usuario, Model $registro): bool
    {
        return $this->viewAny($usuario) && ! self::enUso($registro);
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }

    /** Recuperar de la papelera. */
    public function restore(User $usuario, Model $registro): bool
    {
        return $this->viewAny($usuario);
    }

    /** Borrado definitivo: nunca desde el backend (los pedidos guardarán su referencia). */
    public function forceDelete(User $usuario, Model $registro): bool
    {
        return false;
    }

    public static function enUso(Model $registro): bool
    {
        return match (true) {
            $registro instanceof Familia => $registro->prendas()->withTrashed()->exists(),
            $registro instanceof Color => Variante::where('color_id', $registro->id)->exists() || Foto::where('color_id', $registro->id)->exists(),
            $registro instanceof Talla => Variante::where('talla_id', $registro->id)->exists(),
            $registro instanceof ZonaImpresion => $registro->getConnection()->table('prenda_zona')->where('zona_impresion_id', $registro->id)->exists(),
            $registro instanceof Proveedor => $registro->prendas()->withTrashed()->exists(),
            default => false,
        };
    }
}
