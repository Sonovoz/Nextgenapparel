<?php

namespace App\Http\Controllers\Cuenta;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\Ajuste;
use App\Notifications\Cuenta\SolicitudBorrado;
use App\Support\RutaCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * Solicitud de borrado de la cuenta (paso 5.3): el cliente la pide y la ejecuta el
 * equipo, que conserva lo que obligue la ley (decisión del cliente). Queda en la ficha
 * del cliente, en el registro de actividad y se avisa por correo a la tienda.
 */
class BorradoController
{
    use ConCliente;

    public function solicitar(Request $request): RedirectResponse
    {
        $request->validate(['contrasena_borrado' => ['required', 'string', 'current_password']], ['contrasena_borrado.current_password' => __('cuenta.contrasena_actual_mal')]);
        $cliente = $this->cliente($request);

        if ($cliente->borrado_solicitado_en === null) {
            $cliente->update(['borrado_solicitado_en' => now()]);
            Actividad::apuntar('clientes', 'Borrado de cuenta solicitado', $cliente, quien: $request->user());
            $correo = Ajuste::obtener('tienda.correo_contacto');
            if (filled($correo)) {
                Notification::route('mail', $correo)->notify(new SolicitudBorrado($cliente));
            }
        }

        return redirect(RutaCuenta::url('datos'))->with('estado', __('cuenta.borrado_ok'));
    }
}
