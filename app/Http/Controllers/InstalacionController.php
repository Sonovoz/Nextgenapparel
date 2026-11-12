<?php

namespace App\Http\Controllers;

use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Dominio\Sistema\Instalacion\AccesoTemporal;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Primera instalación (D208): con la clave del archivo temporal se crea el primer
 * superadministrador. Cuando ya existe uno, esta página no existe.
 */
class InstalacionController extends Controller
{
    public function formulario(): View
    {
        abort_unless($this->abierta(), 404);
        AccesoTemporal::preparar();

        return view('instalacion', ['archivo' => AccesoTemporal::ruta()]);
    }

    public function crear(Request $request): RedirectResponse
    {
        abort_unless($this->abierta(), 404);

        $datos = $request->validate([
            'clave' => ['required', 'string'],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ], [], ['clave' => 'clave temporal', 'nombre' => 'nombre', 'email' => 'correo electrónico', 'password' => 'contraseña']);

        if (! AccesoTemporal::comprobar($datos['clave'])) {
            return back()->withInput($request->except('clave', 'password', 'password_confirmation'))
                ->withErrors(['clave' => 'La clave no coincide con la del archivo acceso-temporal.txt.']);
        }

        $usuario = DB::transaction(function () use ($datos): User {
            $usuario = User::create(['name' => $datos['nombre'], 'email' => $datos['email'], 'password' => $datos['password']]);
            $usuario->assignRole(Rol::Superadministrador->value);

            return $usuario;
        });
        AccesoTemporal::cerrar();
        Actividad::apuntar('usuarios', 'Primer superadministrador creado con el acceso temporal', $usuario, quien: $usuario);

        Filament::auth()->login($usuario);
        $request->session()->regenerate();

        return redirect(Filament::getUrl());
    }

    /** Solo mientras no hay superadministrador; si ya lo hay, borra el archivo que pudiera quedar. */
    private function abierta(): bool
    {
        if (AccesoTemporal::pendiente()) {
            return true;
        }
        AccesoTemporal::cerrar();

        return false;
    }
}
