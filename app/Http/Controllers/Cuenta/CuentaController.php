<?php

namespace App\Http\Controllers\Cuenta;

use App\Dominio\Clientes\VincularCliente;
use App\Http\Controllers\DisenoController;
use App\Models\Pedido;
use App\Support\RutaCuenta;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Mi cuenta (paso 5.2; el área completa llega en el 5.3). */
class CuentaController
{
    public function inicio(Request $request, VincularCliente $vincular): View
    {
        $usuario = $request->user();
        $cliente = $vincular($usuario);
        if ($cliente !== null && ! $cliente->activo) {
            session()->now('error', __('cuenta.ficha_inactiva'));
            $cliente = null;
        }

        return view('cuenta.inicio', [
            'usuario' => $usuario,
            'cliente' => $cliente,
            // Mis pedidos (paso 6.6): los de su ficha, los más recientes primero.
            'pedidos' => $cliente === null ? collect() : Pedido::where('cliente_id', $cliente->id)->latest('id')->limit(50)->get(),
            'disenos' => $cliente?->disenos()->latest('id')->get()->map(fn ($d) => DisenoController::datos($d)) ?? collect(),
            'alternos' => RutaCuenta::alternos('inicio'),
        ]);
    }
}
