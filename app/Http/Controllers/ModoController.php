<?php

namespace App\Http\Controllers;

use App\Dominio\Catalogo\ModoCliente;
use App\Dominio\Catalogo\Portada;
use Illuminate\View\View;

/** «Elige tu modo» (D203): /es/modo/eventos… con los packs de ese modo y los genéricos. */
class ModoController
{
    public function ver(string $idioma, string $modo): View
    {
        $modo = ModoCliente::from($modo);

        return view('tienda.modo', [
            'modo' => $modo,
            'packs' => Portada::packs($modo),
            'alternos' => collect(config('idiomas.disponibles'))->keys()->mapWithKeys(fn ($i) => [$i => route('modo', ['idioma' => $i, 'modo' => $modo->value])])->all(),
        ]);
    }
}
