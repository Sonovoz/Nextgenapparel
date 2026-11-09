<?php

namespace App\Http\Controllers\Cuenta;

use App\Support\CamposCuenta;
use App\Support\RutaCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Cambiar la contraseña desde el área (paso 5.3): pide la actual y cierra las demás sesiones. */
class CambiarContrasenaController
{
    public function editar(): View
    {
        return view('cuenta.contrasena', ['alternos' => RutaCuenta::alternos('contrasena')]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'contrasena_actual' => ['required', 'string', 'current_password'],
            'contrasena' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers()],
        ], ['contrasena_actual.current_password' => __('cuenta.contrasena_actual_mal')], CamposCuenta::nombres());

        // Primero la contraseña nueva; después, fuera las demás sesiones (exige la contraseña
        // vigente). Esta sigue: AuthenticateSession guarda la huella nueva al responder (D155).
        $request->user()->forceFill(['password' => Hash::make($datos['contrasena'])])->save();
        Auth::logoutOtherDevices($datos['contrasena']);

        return redirect(RutaCuenta::url('contrasena'))->with('estado', __('cuenta.contrasena_actualizada'));
    }
}
