<?php

namespace App\Http\Controllers\Cuenta;

use App\Http\Controllers\DisenoController;
use App\Support\RutaCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Mis diseños (paso 5.3): los del cliente, con su miniatura, y borrarlos. */
class MisDisenosController
{
    use ConCliente;

    public function lista(Request $request): View
    {
        $cliente = $this->cliente($request);

        return view('cuenta.disenos', [
            'disenos' => $cliente->disenos()->latest('id')->get()->map(fn ($d) => DisenoController::datos($d)),
            'alternos' => RutaCuenta::alternos('disenos'),
        ]);
    }

    public function borrar(Request $request, string $diseno): RedirectResponse
    {
        $this->cliente($request)->disenos()->where('uuid', $diseno)->firstOrFail()->delete();

        return redirect(RutaCuenta::url('disenos'))->with('estado', __('cuenta.diseno_borrado'));
    }
}
