<?php

namespace App\Dominio\Clientes;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

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
        $pendiente = $usuario->registro_pendiente ?? [];

        try {
            $cliente = DB::transaction(function () use ($usuario, $correo, $pendiente): Cliente {
                $invitado = Cliente::where('correo', $correo)->whereNull('user_id')->lockForUpdate()->first();
                $cliente = $invitado ?? new Cliente(['correo' => $correo, 'nombre' => $usuario->name, 'tipo' => TipoCliente::Particular, 'idioma' => app()->getLocale()]);
                $cliente->user_id = $usuario->id;
                // Lo elegido al registrarse no se pierde al unir (revisión N3, M4).
                if (isset($pendiente['tipo'])) {
                    $cliente->tipo = TipoCliente::from($pendiente['tipo']);
                }
                if (! empty($pendiente['marketing_consentido_en']) && $cliente->marketing_consentido_en === null) {
                    $cliente->marketing_consentido_en = $pendiente['marketing_consentido_en'];
                    $cliente->marketing_origen = 'registro';
                }
                $cliente->save();
                $usuario->forceFill(['registro_pendiente' => null])->saveQuietly();

                return $cliente;
            });
        } catch (UniqueConstraintViolationException) {
            // Dos peticiones a la vez: la otra ya la creó (revisión N3, B5).
            $cliente = Cliente::where('user_id', $usuario->id)->firstOrFail();
        }

        $usuario->setRelation('cliente', $cliente);

        return $cliente;
    }
}
