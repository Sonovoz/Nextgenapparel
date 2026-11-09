<?php

namespace App\Dominio\Informes;

use App\Dominio\Pedidos\EstadoPedido;
use App\Models\Factura;
use App\Models\LineaPedido;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Informe de ventas (paso 8.2, D184): sale de las **facturas** emitidas en el periodo, así cuadra con la
 * contabilidad. Facturado (F1/F2) menos rectificado (R1/R5, cancelaciones y reembolsos) = neto.
 */
final class InformeVentas
{
    /**
     * @return array{facturado: int, rectificado: int, neto: int, base: int, iva: int, recargo: int, pedidos: int, ticket: int,
     *   dias: list<array{dia: string, pedidos: int, total: int}>, prendas: list<array{prenda: string, unidades: int, base: int, coste: int, margen: int}>, cupones: list<array{cupon: string, pedidos: int}>, margen: array{ingresos: int, coste: int, margen: int, porcentaje: int, estimadas: int}}
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
            'prendas' => self::lineas($pedidoIds)
                ->groupBy(fn (LineaPedido $l) => $l->nombreEn('es'))
                ->map(fn ($g, $nombre) => ['prenda' => (string) $nombre, 'unidades' => (int) $g->sum('unidades'), 'base' => (int) $g->sum('neto'),
                    'coste' => (int) $g->sum(fn (LineaPedido $l) => self::coste($l)), 'margen' => (int) $g->sum(fn (LineaPedido $l) => $l->neto - self::coste($l))])
                ->sortByDesc('unidades')->values()->take(10)->all(),
            'margen' => self::margen($pedidoIds),
            'cupones' => $ventas->map(fn (Factura $f) => $f->pedido->cupon)->filter()->countBy()
                ->map(fn ($n, $cupon) => ['cupon' => (string) $cupon, 'pedidos' => (int) $n])->sortByDesc('pedidos')->values()->all(),
        ];
    }

    /**
     * Márgenes (v2, D191) de los pedidos facturados en el periodo que no se han cancelado: lo vendido sin IVA ni
     * envío menos el coste de las prendas lisas y de la impresión. Las líneas de antes de guardar el coste se
     * estiman con el coste actual (y se cuentan).
     *
     * @param  list<int>  $pedidoIds
     * @return array{ingresos: int, coste: int, margen: int, porcentaje: int, estimadas: int}
     */
    private static function margen(array $pedidoIds): array
    {
        $lineas = self::lineas($pedidoIds);
        $ingresos = (int) $lineas->sum('neto');
        $coste = (int) $lineas->sum(fn (LineaPedido $l) => self::coste($l));

        return ['ingresos' => $ingresos, 'coste' => $coste, 'margen' => $ingresos - $coste,
            'porcentaje' => $ingresos === 0 ? 0 : intdiv(($ingresos - $coste) * 10000 * 2 + $ingresos, $ingresos * 2), // centésimas de %
            'estimadas' => $lineas->whereNull('coste')->count()];
    }

    /**
     * @param  list<int>  $pedidoIds
     * @return Collection<int, LineaPedido>
     */
    private static function lineas(array $pedidoIds): Collection
    {
        return LineaPedido::whereIn('pedido_id', $pedidoIds)
            ->whereHas('pedido', fn ($p) => $p->whereNotIn('estado', [EstadoPedido::Cancelado->value, EstadoPedido::Reembolsado->value]))
            ->with('impresiones')->get();
    }

    private static function coste(LineaPedido $l): int
    {
        if ($l->coste !== null) {
            return $l->coste;
        }
        $zonas = $l->impresiones->pluck('zona_impresion_id')->filter()->map(fn ($z) => (int) $z)->values()->all();

        return $l->tipo === 'pack'
            ? CosteLinea::pack((array) $l->elecciones, $zonas)
            : CosteLinea::prenda((int) $l->prenda_id, (int) $l->color_id, collect($l->tallas ?? [])->mapWithKeys(fn ($t) => [(int) $t['talla_id'] => (int) $t['unidades']])->all(), $zonas);
    }

    /** Las facturas del periodo, una por fila, para la gestoría (CSV con «;» y BOM, para Excel). */
    public static function csv(CarbonImmutable $desde, CarbonImmutable $hasta): string
    {
        $salida = fopen('php://temp', 'r+');
        $euros = fn (int $c) => number_format($c / 100, 2, ',', '');
        // Un texto que empieza por = + - @ sería una fórmula en Excel (auditoría final, M3).
        $texto = fn (?string $t) => preg_match('/^[=+\-@\t\r]/', (string) $t) === 1 ? "'".$t : (string) $t;
        fputcsv($salida, ['Factura', 'Tipo', 'Fecha', 'Pedido', 'Cliente', 'NIF', 'Base', 'IVA', 'Recargo', 'Total', 'Cupón'], ';');
        Factura::with('pedido')->whereBetween('emitida_en', [$desde->startOfDay(), $hasta->endOfDay()])->orderBy('id')->each(function (Factura $f) use ($salida, $euros, $texto) {
            fputcsv($salida, [$f->codigo, $f->tipo->etiqueta('es'), $f->emitida_en->timezone('Europe/Madrid')->format('d/m/Y'), $f->pedido->numero,
                $texto($f->destinatario['nombre'] ?? ''), $texto($f->destinatario['nif'] ?? ''), $euros($f->base_imponible), $euros($f->iva), $euros($f->recargo), $euros($f->total), $texto($f->pedido->cupon)], ';');
        });
        rewind($salida);

        return "\xEF\xBB\xBF".stream_get_contents($salida);
    }
}
