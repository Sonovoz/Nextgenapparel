<?php

namespace App\Dominio\Clientes;

use App\Models\Cliente;
use App\Models\User;

/**
 * Ficha de cliente de un usuario con el correo verificado (paso 5.2): si había una
 * ficha de invitado con ese correo, pasa a ser suya (con sus pedidos, en la fase 6);
 * si no tenía ninguna (por ejemplo, un empleado que compra), se crea.
 */
final class VincularCliente
{
    public function __invoke(User $usuario): ?Cliente
    {
        if ($usuario->cliente !== null) {
            return $usuario->cliente;
        }
        if (! $usuario->hasVerifiedEmail()) {
            return null;
        }

        $correo = mb_strtolower($usuario->email);
        $invitado = Cliente::where('correo', $correo)->whereNull('user_id')->first();
        if ($invitado !== null) {
            $invitado->update(['user_id' => $usuario->id]);

            return $usuario->setRelation('cliente', $invitado)->cliente;
        }

        return $usuario->cliente()->create([
            'correo' => $correo,
            'tipo' => TipoCliente::Particular,
            'nombre' => $usuario->name,
            'idioma' => app()->getLocale(),
        ]);
    }
}
