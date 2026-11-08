<?php

namespace App\Http\Controllers\Cuenta;

use App\Models\User;
use App\Support\CamposCuenta;
use App\Support\RutaCuenta;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as ReglaContrasena;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Recuperar la contraseña desde la tienda (paso 5.2). */
class ContrasenaController
{
    public function formularioRecuperar(): View
    {
        return view('cuenta.recuperar', ['alternos' => RutaCuenta::alternos('recuperar')]);
    }

    /** Siempre la misma respuesta: no se desvela si hay una cuenta con ese correo. */
    public function enviarEnlace(Request $request): RedirectResponse
    {
        $datos = $request->validate(['correo' => ['required', 'string', 'email', 'max:255']], [], CamposCuenta::nombres());
        Password::sendResetLink(['email' => mb_strtolower(trim($datos['correo'])), 'activo' => true]);

        return back()->with('estado', __('cuenta.enlace_enviado'));
    }

    public function formularioRestablecer(Request $request, string $token): View
    {
        return view('cuenta.restablecer', [
            'token' => $token,
            'correo' => (string) $request->query('correo'),
            'alternos' => RutaCuenta::alternos('restablecer', ['token' => $token]),
        ]);
    }

    public function restablecer(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'string'],
            'correo' => ['required', 'string', 'email'],
            'contrasena' => ['required', 'string', 'confirmed', ReglaContrasena::min(10)->letters()->numbers()],
        ], [], CamposCuenta::nombres());

        $estado = Password::reset(
            ['email' => mb_strtolower(trim($datos['correo'])), 'password' => $datos['contrasena'], 'password_confirmation' => $datos['contrasena'], 'token' => $datos['token']],
            function (User $usuario, string $contrasena): void {
                $usuario->forceFill(['password' => Hash::make($contrasena), 'remember_token' => Str::random(60)]);
                // El enlace llegó a su correo: queda verificado.
                if (! $usuario->hasVerifiedEmail()) {
                    $usuario->email_verified_at = now();
                }
                $usuario->save();
                event(new PasswordReset($usuario));
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['correo' => __('cuenta.enlace_no_valido')]);
        }

        return redirect(RutaCuenta::url('entrar'))->with('estado', __('cuenta.contrasena_cambiada'));
    }
}
