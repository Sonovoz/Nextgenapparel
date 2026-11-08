<?php

namespace App\Http\Controllers\Cuenta;

use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\Ajuste;
use App\Models\User;
use App\Notifications\Cuenta\AcuseBorrado;
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
            // Nunca sin aviso (revisión N3 del 5.3, A3): correo de contacto, si no el de alertas
            // y, si tampoco hay, los administradores. Y el cliente recibe su acuse.
            foreach (self::destinatarios() as $correo) {
                Notification::route('mail', $correo)->notify(new SolicitudBorrado($cliente));
            }
            $request->user()->notify((new AcuseBorrado)->locale($cliente->idioma));
        }

        return redirect(RutaCuenta::url('datos'))->with('estado', __('cuenta.borrado_ok'));
    }

    /** @return list<string> */
    public static function destinatarios(): array
    {
        $correo = Ajuste::obtener('tienda.correo_contacto') ?: config('errores.correo_alertas');
        if (filled($correo)) {
            return [$correo];
        }

        return User::role([Rol::Superadministrador->value, Rol::Administrador->value])->where('activo', true)->pluck('email')->all();
    }
}
