<?php

namespace App\Http\Controllers\Cuenta;

use App\Dominio\Clientes\Nif;
use App\Dominio\Clientes\TipoCliente;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\Cliente;
use App\Models\User;
use App\Notifications\Cuenta\AvisoCambioCorreo;
use App\Notifications\Cuenta\ConfirmarNuevoCorreo;
use App\Support\CamposCuenta;
use App\Support\RutaCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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
            'recargo_equivalencia' => ['nullable', 'boolean'],
            'contrasena_actual' => ['nullable', 'string'],
        ], [], CamposCuenta::nombres() + ['telefono' => mb_strtolower(__('cuenta.telefono'))]);

        $correoNuevo = $datos['correo'] !== $usuario->email ? $datos['correo'] : null;
        if ($correoNuevo !== null) {
            if (! Hash::check((string) ($datos['contrasena_actual'] ?? ''), $usuario->password)) {
                throw ValidationException::withMessages(['contrasena_actual' => __('cuenta.contrasena_actual_mal')]);
            }
            if (self::correoOcupado($correoNuevo, $usuario, $cliente)) {
                throw ValidationException::withMessages(['correo' => __('cuenta.correo_no_disponible')]);
            }
        }

        DB::transaction(function () use ($cliente, $usuario, $datos, $correoNuevo): void {
            $cliente = Cliente::whereKey($cliente->id)->lockForUpdate()->first();
            $antes = ['tipo' => $cliente->tipo->value, 'nif' => $cliente->nif];
            $marketing = (bool) ($datos['marketing'] ?? false);
            $cliente->fill([
                'nombre' => trim($datos['nombre']),
                'tipo' => TipoCliente::from($datos['tipo']),
                'telefono' => ($datos['telefono'] ?? null) ?: null,
                'idioma' => $datos['idioma'],
                'nif' => ($datos['nif'] ?? null) ?: null,
            ]);
            // El consentimiento guarda cuándo y dónde se dio; al retirarlo, se borra.
            if ($marketing && $cliente->marketing_consentido_en === null) {
                $cliente->marketing_consentido_en = now();
                $cliente->marketing_origen = 'area_cliente';
            } elseif (! $marketing) {
                $cliente->marketing_consentido_en = null;
                $cliente->marketing_origen = null;
            }

            // Recargo de equivalencia (Ajustes → General): si lo indica el cliente, se aplica lo que marque
            // (solo autónomos y empresas). Si lo indica el equipo, lo validó para un NIF y un tipo concretos:
            // si cambian, se retira para que el equipo lo revise. Un particular nunca lo tiene.
            $despues = ['tipo' => $cliente->tipo->value, 'nif' => $cliente->nif];
            $recargoAntes = (bool) $cliente->getOriginal('recargo_equivalencia');
            if ($cliente->puedeIndicarRecargo()) {
                $cliente->recargo_equivalencia = (bool) ($datos['recargo_equivalencia'] ?? false);
            } elseif (! $cliente->tipo->admiteRecargo() || $antes !== $despues) {
                $cliente->recargo_equivalencia = false;
            }

            // Todo cambio fiscal queda registrado, en los dos modos (revisión N3 del 5.4, M1).
            $recargoDespues = (bool) $cliente->recargo_equivalencia;
            if ($antes !== $despues || $recargoAntes !== $recargoDespues) {
                $descripcion = match (true) {
                    $antes === $despues => $recargoDespues ? 'Recargo de equivalencia activado por el cliente' : 'Recargo de equivalencia retirado por el cliente',
                    $recargoAntes && ! $recargoDespues => 'Datos fiscales cambiados por el cliente: recargo de equivalencia retirado',
                    ! $recargoAntes && $recargoDespues => 'Datos fiscales cambiados por el cliente: recargo de equivalencia activado',
                    default => 'Datos fiscales cambiados por el cliente',
                };
                Actividad::apuntar('clientes', $descripcion, $cliente, [
                    'old' => [...$antes, 'recargo_equivalencia' => $recargoAntes],
                    'attributes' => [...$despues, 'recargo_equivalencia' => $recargoDespues],
                ], $usuario);
            }
            $cliente->save();

            $usuario->name = trim($datos['nombre']);
            // El correo no cambia hasta que se confirma el nuevo (A1).
            $usuario->correo_pendiente = $correoNuevo ?? $usuario->correo_pendiente;
            $usuario->save();
        });

        if ($correoNuevo !== null) {
            Notification::route('mail', $correoNuevo)->notify((new ConfirmarNuevoCorreo($usuario, $correoNuevo))->locale($cliente->fresh()->idioma));
            $usuario->notify((new AvisoCambioCorreo($correoNuevo))->locale($cliente->fresh()->idioma));
        }

        return redirect(RutaCuenta::url('datos'))->with('estado', __($correoNuevo !== null ? 'cuenta.correo_cambiado' : 'cuenta.datos_guardados'));
    }

    /** Ocupado por otra cuenta, otra ficha (también de invitado) o pendiente de otro usuario. */
    public static function correoOcupado(string $correo, User $usuario, Cliente $cliente): bool
    {
        return User::where('email', $correo)->whereKeyNot($usuario->id)->exists()
            || User::where('correo_pendiente', $correo)->whereKeyNot($usuario->id)->exists()
            || Cliente::where('correo', $correo)->whereKeyNot($cliente->id)->exists();
    }
}
