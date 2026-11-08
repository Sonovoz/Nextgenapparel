<?php

namespace App\Http\Controllers\Cuenta;

use App\Dominio\Clientes\RegistrarCliente;
use App\Dominio\Clientes\TipoCliente;
use App\Dominio\Disenos\Propietario;
use App\Dominio\Disenos\TraspasarDisenos;
use App\Support\CamposCuenta;
use App\Support\RutaCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Registro de clientes (paso 5.2, D153): lo mínimo; los datos fiscales, al cerrar el pedido. */
class RegistroController
{
    public function formulario(): View
    {
        return view('cuenta.registro', ['alternos' => RutaCuenta::alternos('registro')]);
    }

    public function registrar(Request $request, RegistrarCliente $registrar, TraspasarDisenos $traspasar): RedirectResponse
    {
        $request->merge(['correo' => mb_strtolower(trim((string) $request->input('correo')))]);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'correo' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'contrasena' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers()],
            'tipo' => ['required', Rule::enum(TipoCliente::class)],
            'marketing' => ['nullable', 'boolean'],
        ], [
            'correo.unique' => __('cuenta.correo_registrado', ['entrar' => RutaCuenta::url('entrar')]),
        ], CamposCuenta::nombres());

        $usuario = $registrar(trim($datos['nombre']), $datos['correo'], $datos['contrasena'], TipoCliente::from($datos['tipo']), (bool) ($datos['marketing'] ?? false), app()->getLocale());

        $propietario = Propietario::hash($request, crear: false);
        Auth::login($usuario);
        $request->session()->regenerate();
        if ($usuario->cliente !== null && $propietario !== null) {
            $traspasar($propietario, $usuario->cliente);
        }
        $usuario->sendEmailVerificationNotification();

        return redirect(RutaCuenta::url('inicio'))->with('estado', __('cuenta.registrado'));
    }
}
