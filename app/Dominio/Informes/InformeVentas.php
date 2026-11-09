<?php

namespace App\Dominio\Informes;

use App\Models\Factura;
use App\Models\LineaPedido;
use Carbon\CarbonImmutable;

/**
 * Informe de ventas (paso 8.2, D184): sale de las **facturas** emitidas en el periodo, así cuadra con la
 * contabilidad. Facturado (F1/F2) menos rectificado (R1/R5, cancelaciones y reembolsos) = neto.
 */
final class InformeVentas
{
    /**
     * @return array{facturado: int, rectificado: int, neto: int, base: int, iva: int, recargo: int, pedidos: int, ticket: int,
     *   dias: list<array{dia: string, pedidos: int, total: int}>, prendas: list<array{prenda: string, unidades: int, base: int}>, cupones: list<array{cupon: string, pedidos: int}>}
     */
    public static function datos(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $facturas = Factura::with('pedido')->whereBetween('emitida_en', [$desde->startOfDay(), $hasta->endOfDay()])->orderBy('emitida_en')->get();
        $ventas = $facturas->filter(fn (Factura $f) => ! $f->tipo->esRectificativa());
        $rectificativas = $facturas->filter(fn (Factura $f) => $f->tipo->esRectificativa());
        $facturado = (int) $ventas->sum('total');
        $pedidoIds = $ventas->pluck('pedido_id')->all();

        return [
            'facturado' => $facturado,
            'rectificado' => (int) -$rectificativas->sum('total'),
            'neto' => (int) $facturas->sum('total'),
            'base' => (int) $facturas->sum('base_imponible'),
            'iva' => (int) $facturas->sum('iva'),
            'recargo' => (int) $facturas->sum('recargo'),
            'pedidos' => $ventas->count(),
            'ticket' => $ventas->isEmpty() ? 0 : intdiv($facturado * 2 + $ventas->count(), $ventas->count() * 2),
            'dias' => $ventas->groupBy(fn (Factura $f) => $f->emitida_en->timezone('Europe/Madrid')->format('Y-m-d'))
                ->map(fn ($g, $dia) => ['dia' => (string) $dia, 'pedidos' => $g->count(), 'total' => (int) $g->sum('total')])->values()->all(),
            'prendas' => LineaPedido::whereIn('pedido_id', $pedidoIds)->get()
                ->groupBy(fn (LineaPedido $l) => $l->nombreEn('es'))
                ->map(fn ($g, $nombre) => ['prenda' => (string) $nombre, 'unidades' => (int) $g->sum('unidades'), 'base' => (int) $g->sum('neto')])
                ->sortByDesc('unidades')->values()->take(10)->all(),
            'cupones' => $ventas->map(fn (Factura $f) => $f->pedido->cupon)->filter()->countBy()
                ->map(fn ($n, $cupon) => ['cupon' => (string) $cupon, 'pedidos' => (int) $n])->sortByDesc('pedidos')->values()->all(),
        ];
    }

    /** Las facturas del periodo, una por fila, para la gestoría (CSV con «;» y BOM, para Excel). */
    public static function csv(CarbonImmutable $desde, CarbonImmutable $hasta): string
    {
        $salida = fopen('php://temp', 'r+');
        $euros = fn (int $c) => number_format($c / 100, 2, ',', '');
        fputcsv($salida, ['Factura', 'Tipo', 'Fecha', 'Pedido', 'Cliente', 'NIF', 'Base', 'IVA', 'Recargo', 'Total', 'Cupón'], ';');
        Factura::with('pedido')->whereBetween('emitida_en', [$desde->startOfDay(), $hasta->endOfDay()])->orderBy('id')->each(function (Factura $f) use ($salida, $euros) {
            fputcsv($salida, [$f->codigo, $f->tipo->etiqueta('es'), $f->emitida_en->timezone('Europe/Madrid')->format('d/m/Y'), $f->pedido->numero,
                $f->destinatario['nombre'] ?? '', $f->destinatario['nif'] ?? '', $euros($f->base_imponible), $euros($f->iva), $euros($f->recargo), $euros($f->total), $f->pedido->cupon ?? ''], ';');
        });
        rewind($salida);

        return "\xEF\xBB\xBF".stream_get_contents($salida);
    }
}
