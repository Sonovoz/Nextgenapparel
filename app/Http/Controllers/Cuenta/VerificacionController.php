<?php

namespace App\Http\Controllers\Cuenta;

use App\Dominio\Carrito\FusionarCarrito;
use App\Dominio\Clientes\VincularCliente;
use App\Dominio\Disenos\Propietario;
use App\Dominio\Disenos\TraspasarDisenos;
use App\Models\Cliente;
use App\Models\User;
use App\Support\RutaCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Verificación del correo (paso 5.2, D153): obligatoria antes de cerrar un pedido. */
class VerificacionController
{
    /**
     * El enlace del correo. No exige haber entrado: se abre a menudo en otro navegador
     * o en el móvil, y la firma de la URL ya demuestra que llegó a ese correo.
     */
    public function verificar(Request $request, string $id, string $hash, VincularCliente $vincular, TraspasarDisenos $traspasar, FusionarCarrito $fusionar): RedirectResponse
    {
        $destino = auth()->check() ? RutaCuenta::url('inicio') : RutaCuenta::url('entrar');
        $usuario = User::find($id);

        if (! $request->hasValidSignature() || $usuario === null || ! hash_equals(sha1($usuario->getEmailForVerification()), $hash)) {
            return redirect($destino)->with('error', __('cuenta.verificacion_caducada'));
        }

        if (! $usuario->hasVerifiedEmail()) {
            $usuario->markEmailAsVerified();
        }
        $cliente = $vincular($usuario);

        // Si se verifica en el mismo navegador, los diseños que subió antes de tener ficha pasan a ella (B4).
        $propietario = Propietario::hash($request, crear: false);
        if ($cliente !== null && $propietario !== null && auth()->id() === $usuario->id) {
            $traspasar($propietario, $cliente);
        }
        if ($cliente !== null && auth()->id() === $usuario->id) {
            $fusionar($request, $cliente);
        }

        return redirect($destino)->with('estado', __('cuenta.correo_verificado'));
    }

    /**
     * Confirmar el cambio de correo (revisión N3 del paso 5.3, A1): solo con el enlace firmado
     * que llegó al correo nuevo cambian el de la cuenta y el de la ficha.
     */
    public function confirmarCorreo(Request $request, string $id, string $hash): RedirectResponse
    {
        $destino = auth()->check() ? RutaCuenta::url('datos') : RutaCuenta::url('entrar');
        $usuario = User::find($id);
        $nuevo = $usuario?->correo_pendiente;

        if (! $request->hasValidSignature() || $nuevo === null || ! hash_equals(sha1($nuevo), $hash)) {
            return redirect($destino)->with('error', __('cuenta.verificacion_caducada'));
        }
        $cliente = $usuario->cliente;
        if (DatosController::correoOcupado($nuevo, $usuario, $cliente ?? new Cliente)) {
            $usuario->forceFill(['correo_pendiente' => null])->save();

            return redirect($destino)->with('error', __('cuenta.correo_no_disponible'));
        }

        DB::transaction(function () use ($usuario, $cliente, $nuevo): void {
            $usuario->forceFill(['email' => $nuevo, 'correo_pendiente' => null, 'email_verified_at' => now()])->save();
            $cliente?->update(['correo' => $nuevo]);
        });

        return redirect($destino)->with('estado', __('cuenta.correo_verificado'));
    }

    public function reenviar(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('estado', __('cuenta.verificacion_reenviada'));
    }
}
