<?php

namespace App\Http\Controllers\Backend;

use App\Dominio\Produccion\HojaCompra;
use App\Dominio\Produccion\OrdenTrabajo;
use App\Dominio\Produccion\PaqueteProduccion;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Pedido;
use Filament\Facades\Filament;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documentos de producción (paso 6.5b): orden de trabajo, paquete ZIP y hoja de compra.
 * Para quien tiene la sección Producción (también el rol Producción, que no ve los datos
 * de facturación de los pedidos). A los demás, 404.
 */
class ProduccionController
{
    public function orden(Pedido $pedido): Response
    {
        $this->comprobarAcceso();

        return response(OrdenTrabajo::pdf($pedido), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pedido->numero.'_orden-de-trabajo.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function paquete(Pedido $pedido): Response
    {
        $this->comprobarAcceso();
        Actividad::apuntar('produccion', 'Paquete de producción descargado', $pedido);

        return response()->download(PaqueteProduccion::crear($pedido), $pedido->numero.'_produccion.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    public function hojaCompra(): Response
    {
        $this->comprobarAcceso();

        return response(HojaCompra::csv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="hoja-de-compra-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    private function comprobarAcceso(): void
    {
        $usuario = auth()->user();
        abort_unless($usuario !== null && $usuario->canAccessPanel(Filament::getPanel('admin')) && $usuario->can(GrupoNavegacion::Produccion->permiso()), 404);
    }
}
