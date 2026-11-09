<?php

namespace App\Http\Controllers;

use App\Dominio\Carrito\CarritoActual;
use App\Dominio\Disenos\Dueno;
use App\Dominio\Facturacion\Emisor;
use App\Dominio\Precios\Formato;
use App\Dominio\Presupuestos\CrearPresupuesto;
use App\Dominio\Presupuestos\PasarAlCarrito;
use App\Models\Presupuesto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Presupuestos en PDF (D187): desde el carrito, con su página (por su enlace) para descargarlo o pasarlo al carrito. */
class PresupuestoController
{
    public static function url(string $accion, Presupuesto $presupuesto, ?string $idioma = null): string
    {
        return route('presupuestos.'.($idioma ?? app()->getLocale()).'.'.$accion, ['presupuesto' => $presupuesto->uuid]);
    }

    public function crear(Request $request, CrearPresupuesto $crear): RedirectResponse
    {
        $carrito = CarritoActual::de($request);
        $presupuesto = $carrito === null ? null : $crear($carrito, $request->user()?->cliente, app()->getLocale());
        if ($presupuesto === null) {
            return redirect(CarritoController::url('ver'))->with('estado', __('presupuestos.no_se_puede'));
        }

        return redirect(self::url('ver', $presupuesto));
    }

    public function ver(Presupuesto $presupuesto): View
    {
        return view('presupuestos.ver', ['presupuesto' => $presupuesto,
            'alternos' => collect(config('idiomas.disponibles'))->keys()->mapWithKeys(fn ($i) => [$i => self::url('ver', $presupuesto, $i)])->all()]);
    }

    public function pdf(Presupuesto $presupuesto): Response
    {
        $pdf = Pdf::loadView('pdf.presupuesto', ['presupuesto' => $presupuesto, 'emisor' => Emisor::datos(), 'enlace' => self::url('ver', $presupuesto, $presupuesto->idioma)])
            ->setPaper('a4')->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true]);

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="presupuesto-'.$presupuesto->numero.'.pdf"', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function pasar(Request $request, Presupuesto $presupuesto, PasarAlCarrito $pasar): RedirectResponse
    {
        if (! $presupuesto->vigente()) {
            return redirect(self::url('ver', $presupuesto))->with('estado', __('presupuestos.caducado'));
        }
        $carrito = CarritoActual::de($request, crear: true);
        $dueno = Dueno::de($request);
        abort_if($carrito === null || $dueno === null, 404);
        $vacio = $carrito->lineas()->doesntExist();
        $r = $pasar($presupuesto, $carrito, $dueno);

        $mensaje = trans_choice('presupuestos.pasado', $r['anadidas'], ['n' => $r['anadidas']]);
        if ($r['omitidas'] > 0) {
            $mensaje .= ' '.trans_choice('presupuestos.omitidas', $r['omitidas'], ['n' => $r['omitidas']]);
        }
        if ($vacio && $r['anadidas'] > 0 && $r['total'] !== $presupuesto->total) {
            $mensaje .= ' '.__('presupuestos.precio_cambiado', ['antes' => Formato::euros($presupuesto->total), 'ahora' => Formato::euros($r['total'])]);
        }

        return redirect(CarritoController::url('ver'))->with('estado', $mensaje);
    }
}
