<?php

namespace App\Http\Controllers\Cuenta;

use App\Dominio\Clientes\VincularCliente;
use App\Models\Cliente;
use App\Support\RutaCuenta;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Ficha del cliente que ha entrado (paso 5.3). Todo lo del área se busca a través de
 * ella: nadie llega a direcciones o diseños de otro cambiando el identificador de la URL.
 */
trait ConCliente
{
    protected function cliente(Request $request): Cliente
    {
        $cliente = app(VincularCliente::class)($request->user());
        if ($cliente === null) {
            // Registrado con el correo de una ficha de invitado y aún sin verificar.
            throw new HttpResponseException(redirect(RutaCuenta::url('inicio'))->with('error', __('cuenta.necesita_ficha')));
        }

        if (! $cliente->activo) {
            // Ficha desactivada por el equipo (también la de un empleado que compra): sin área ni compras (B1).
            throw new HttpResponseException(redirect(RutaCuenta::url('inicio'))->with('error', __('cuenta.ficha_inactiva')));
        }

        return $cliente;
    }
}
