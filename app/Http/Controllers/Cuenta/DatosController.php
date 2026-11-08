<?php

namespace App\Http\Controllers\Cuenta;

use App\Dominio\Clientes\Nif;
use App\Dominio\Clientes\TipoCliente;
use App\Models\Cliente;
use App\Models\User;
use App\Support\CamposCuenta;
use App\Support\RutaCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Mis datos (paso 5.3): contacto, idioma, marketing y NIF. El recargo lo activa el equipo. */
class DatosController
{
    use ConCliente;

    public function editar(Request $request): View
    {
        return view('cuenta.datos', ['cliente' => $this->cliente($request), 'usuario' => $request->user(), 'alternos' => RutaCuenta::alternos('datos')]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $cliente = $this->cliente($request);
        $usuario = $request->user();
        $request->merge(['correo' => mb_strtolower(trim((string) $request->input('correo'))), 'nif' => Nif::normalizar((string) $request->input('nif'))]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'correo' => ['required', 'string', 'email', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoCliente::class)],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[+0-9 ()\-]{6,30}$/'],
            'idioma' => ['required', Rule::in(array_keys(config('idiomas.disponibles')))],
            'nif' => ['nullable', 'string', 'max:20', fn ($a, $v, $fallo) => $v !== '' && ! Nif::valido($v) ? $fallo(__('cuenta.nif_no_valido')) : null],
            'marketing' => ['nullable', 'boolean'],
            'contrasena_actual' => ['nullable', 'string'],
        ], [], CamposCuenta::nombres() + ['telefono' => mb_strtolower(__('cuenta.telefono'))]);

        $cambiaCorreo = $datos['correo'] !== $usuario->email;
        if ($cambiaCorreo) {
            if (! Hash::check((string) ($datos['contrasena_actual'] ?? ''), $usuario->password)) {
                throw ValidationException::withMessages(['contrasena_actual' => __('cuenta.contrasena_actual_mal')]);
            }
            $ocupado = User::where('email', $datos['correo'])->whereKeyNot($usuario->id)->exists()
                || Cliente::where('correo', $datos['correo'])->whereKeyNot($cliente->id)->exists();
            if ($ocupado) {
                throw ValidationException::withMessages(['correo' => __('cuenta.correo_no_disponible')]);
            }
        }

        DB::transaction(function () use ($cliente, $usuario, $datos, $cambiaCorreo): void {
            $marketing = (bool) ($datos['marketing'] ?? false);
            $cliente->fill([
                'nombre' => trim($datos['nombre']),
                'correo' => $datos['correo'],
                'tipo' => TipoCliente::from($datos['tipo']),
                'telefono' => $datos['telefono'] ?: null,
                'idioma' => $datos['idioma'],
                'nif' => $datos['nif'] ?: null,
            ]);
            // El consentimiento guarda cuándo y dónde se dio; al retirarlo, se borra.
            if ($marketing && $cliente->marketing_consentido_en === null) {
                $cliente->marketing_consentido_en = now();
                $cliente->marketing_origen = 'area_cliente';
            } elseif (! $marketing) {
                $cliente->marketing_consentido_en = null;
                $cliente->marketing_origen = null;
            }
            // Un particular no tiene recargo de equivalencia.
            if (! $cliente->tipo->admiteRecargo()) {
                $cliente->recargo_equivalencia = false;
            }
            $cliente->save();

            $usuario->name = trim($datos['nombre']);
            if ($cambiaCorreo) {
                $usuario->email = $datos['correo'];
                $usuario->email_verified_at = null;
            }
            $usuario->save();
        });

        if ($cambiaCorreo) {
            $usuario->sendEmailVerificationNotification();
        }

        return redirect(RutaCuenta::url('datos'))->with('estado', __($cambiaCorreo ? 'cuenta.correo_cambiado' : 'cuenta.datos_guardados'));
    }
}
