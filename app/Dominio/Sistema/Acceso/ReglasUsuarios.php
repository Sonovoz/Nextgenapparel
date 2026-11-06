<?php

namespace App\Dominio\Sistema\Acceso;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Reglas que impiden dejar el backend sin nadie que lo pueda administrar.
 *
 * - Nadie puede cambiar su propio rol ni desactivarse a sí mismo.
 * - Siempre debe quedar al menos un superadministrador activo.
 */
class ReglasUsuarios
{
    /**
     * @param  User|null  $usuario  Usuario que se modifica (null si se está creando).
     *
     * @throws ValidationException
     */
    public function validar(User $editor, ?User $usuario, Rol $nuevoRol, bool $nuevoActivo): void
    {
        if ($usuario === null) {
            return;
        }

        $errores = [];

        if ($usuario->is($editor)) {
            if ($usuario->rol() !== $nuevoRol) {
                $errores['data.rol'] = 'No puedes cambiar tu propio rol. Pídeselo a otro superadministrador.';
            }

            if (! $nuevoActivo) {
                $errores['data.activo'] = 'No puedes desactivar tu propio usuario.';
            }
        }

        $dejaDeSerSuperadministradorActivo = $usuario->activo
            && $usuario->esSuperadministrador()
            && ($nuevoRol !== Rol::Superadministrador || ! $nuevoActivo);

        if ($dejaDeSerSuperadministradorActivo && $this->superadministradoresActivos() <= 1) {
            $errores['data.rol'] ??= 'Debe quedar al menos un superadministrador activo.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    private function superadministradoresActivos(): int
    {
        return User::role(Rol::Superadministrador->value)->where('activo', true)->count();
    }
}
