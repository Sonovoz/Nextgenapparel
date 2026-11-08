<?php

namespace App\Http\Controllers\Cuenta;

use App\Dominio\Clientes\VincularCliente;
use App\Dominio\Disenos\Propietario;
use App\Dominio\Disenos\TraspasarDisenos;
use App\Support\CamposCuenta;
use App\Support\RutaCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Entrar y salir de la cuenta en la tienda (paso 5.2). */
class AccesoController
{
    private const INTENTOS = 5;

    public function formulario(): View
    {
        return view('cuenta.entrar', ['alternos' => RutaCuenta::alternos('entrar')]);
    }

    public function entrar(Request $request, VincularCliente $vincular, TraspasarDisenos $traspasar): RedirectResponse
    {
        $datos = $request->validate([
            'correo' => ['required', 'string', 'email', 'max:255'],
            'contrasena' => ['required', 'string'],
        ], [], CamposCuenta::nombres());
        $correo = mb_strtolower(trim($datos['correo']));
        $clave = 'entrar|'.$correo.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($clave, self::INTENTOS)) {
            throw ValidationException::withMessages(['correo' => __('cuenta.demasiados_intentos', ['segundos' => RateLimiter::availableIn($clave)])]);
        }

        // Mismo mensaje si el correo no existe, la contraseña no cuadra o la cuenta está desactivada.
        if (! Auth::attempt(['email' => $correo, 'password' => $datos['contrasena'], 'activo' => true], $request->boolean('recordar'))) {
            RateLimiter::hit($clave, 60);
            throw ValidationException::withMessages(['correo' => __('cuenta.credenciales')]);
        }

        RateLimiter::clear($clave);
        $propietario = Propietario::hash($request, crear: false); // antes de renovar la sesión
        $request->session()->regenerate();

        $cliente = $vincular($request->user());
        if ($cliente !== null && $propietario !== null) {
            $traspasar($propietario, $cliente);
        }

        return redirect(self::destino($request));
    }

    /**
     * Vuelve a donde iba, pero solo si es una página de la tienda (/{idioma}/…): tras una
     * visita a /admin, un cliente acababa en una página prohibida (revisión N3, M5).
     */
    public static function destino(Request $request): string
    {
        $intento = (string) $request->session()->pull('url.intended', '');
        $ruta = (string) parse_url($intento, PHP_URL_PATH);
        $mismoSitio = str_starts_with($intento, url('/').'/') || str_starts_with($intento, '/');
        $deLaTienda = (bool) preg_match('#^/('.implode('|', array_keys(config('idiomas.disponibles'))).')(/|$)#', $ruta);

        return $mismoSitio && $deLaTienda ? $intento : RutaCuenta::url('inicio');
    }

    public function salir(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portada')->with('estado', __('cuenta.has_salido'));
    }
}
