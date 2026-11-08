<?php

namespace App\Http\Controllers\Cuenta;

use App\Dominio\Clientes\VincularCliente;
use App\Dominio\Disenos\Propietario;
use App\Dominio\Disenos\TraspasarDisenos;
use App\Models\User;
use App\Support\RutaCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Verificación del correo (paso 5.2, D153): obligatoria antes de cerrar un pedido. */
class VerificacionController
{
    /**
     * El enlace del correo. No exige haber entrado: se abre a menudo en otro navegador
     * o en el móvil, y la firma de la URL ya demuestra que llegó a ese correo.
     */
    public function verificar(Request $request, string $id, string $hash, VincularCliente $vincular, TraspasarDisenos $traspasar): RedirectResponse
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
