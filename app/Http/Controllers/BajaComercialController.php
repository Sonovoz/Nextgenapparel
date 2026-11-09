<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Darse de baja de los correos comerciales con el enlace firmado de cada correo (v2, D189), sin entrar. */
class BajaComercialController
{
    public function __invoke(Request $request, Cliente $cliente): View
    {
        abort_unless($request->hasValidSignature(), 403);
        $cliente->update(['marketing_consentido_en' => null, 'marketing_origen' => null]);
        app()->setLocale(in_array($cliente->idioma, array_keys(config('idiomas.disponibles')), true) ? (string) $cliente->idioma : 'es');

        return view('marketing.baja');
    }
}
