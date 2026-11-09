<?php

namespace App\Http\Controllers;

use App\Dominio\Asistente\AsistenteVenta;
use App\Dominio\Asistente\IaNoDisponible;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** El chat del asistente de venta (v2, D194): solo con cuenta; si la IA falla, 503 y el widget se oculta. */
class AsistenteController
{
    public function __invoke(Request $request, AsistenteVenta $asistente): JsonResponse
    {
        $cliente = $request->user()?->cliente;
        abort_if($cliente === null, 403);
        $datos = $request->validate([
            'mensajes' => ['required', 'array', 'min:1', 'max:20'],
            'mensajes.*.rol' => ['required', 'in:cliente,asistente'],
            'mensajes.*.texto' => ['required', 'string', 'max:1000'],
        ]);
        try {
            return response()->json(['respuesta' => $asistente($cliente, array_values($datos['mensajes']), app()->getLocale())]);
        } catch (IaNoDisponible) {
            return response()->json(['respuesta' => null], 503);
        }
    }
}
