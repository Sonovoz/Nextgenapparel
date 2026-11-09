<?php

namespace App\Http\Controllers\Backend;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\ImpresionPedido;
use App\Models\MaquetaPedido;
use App\Models\Pedido;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Archivos de un pedido para el backend (paso 6.3): sus maquetas y el original de cada
 * diseño, del disco privado «pedidos». Solo con permiso (PedidoPolicy); a los demás, 404.
 */
class ArchivoPedidoController
{
    public function maqueta(Pedido $pedido, MaquetaPedido $maqueta): Response
    {
        $this->comprobarAcceso($pedido);
        abort_unless($maqueta->linea?->pedido_id === $pedido->id, 404);

        return Storage::disk(Pedido::DISCO)->response($maqueta->ruta, null, ['Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function original(Pedido $pedido, ImpresionPedido $impresion): Response
    {
        $this->comprobarAcceso($pedido);
        abort_unless($impresion->archivo !== null && $impresion->linea?->pedido_id === $pedido->id, 404);

        Actividad::apuntar('pedidos', 'Original de un diseño del pedido descargado', $pedido);
        $nombre = $pedido->numero.'_'.($impresion->zona_nombre['es'] ?? 'zona').'_'.(pathinfo((string) $impresion->nombre_original, PATHINFO_FILENAME) ?: 'diseno').'.png';

        return Storage::disk(Pedido::DISCO)->download($impresion->archivo, str_replace(['/', '\\'], '-', $nombre), [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Como en los diseños: usuario activo con acceso al panel y permiso del pedido (estas rutas no pasan por Filament). */
    private function comprobarAcceso(Pedido $pedido): void
    {
        $usuario = auth()->user();

        abort_unless($usuario !== null && $usuario->canAccessPanel(Filament::getPanel('admin')) && $usuario->can('view', $pedido), 404);
    }
}
