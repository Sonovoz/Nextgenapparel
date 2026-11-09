<?php

namespace App\Dominio\Facturacion;

use App\Dominio\Pedidos\Numeracion;
use App\Dominio\Precios\Formato;
use App\Models\Factura;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Emite las facturas de un pedido (paso 6.5a, D175), portado de Clave de Alma (ADR 0018):
 *
 * - **Factura** al darlo por pagado: completa (F1) si hay NIF, simplificada (F2) si no.
 *   Los importes y el desglose de impuestos (IVA y recargo por tipo) son los del pedido,
 *   tal como los calculó el motor: la factura cuadra con él al céntimo.
 * - **Rectificativa** por diferencias, en negativo, al cancelar un pedido ya facturado (R1 de una
 *   completa, R5 de una simplificada): del total, o del resto si hubo reembolsos parciales.
 * - **Rectificativa parcial** por un reembolso de una incidencia (D178).
 * - Idempotente; numeración correlativa por serie y año y huella encadenada bajo bloqueo.
 * - Sin datos fiscales completos no se factura (EmisorIncompleto): `facturas:emitir-pendientes`.
 */
final class EmitirFactura
{
    public function delPedido(Pedido $pedido): Factura
    {
        $existente = Factura::where('pedido_id', $pedido->id)->whereIn('tipo', [TipoFactura::Completa->value, TipoFactura::Simplificada->value])->first();
        if ($existente !== null) {
            return $existente;
        }
        $pedido->loadMissing('lineas.impresiones');
        $tipo = filled($pedido->nif) ? TipoFactura::Completa : TipoFactura::Simplificada;

        $lineas = [];
        foreach ($pedido->lineas as $linea) {
            $tallas = collect($linea->tallas ?? [])->map(fn ($t) => $t['nombre'].' × '.$t['unidades'])->join(', ');
            $zonas = $linea->impresiones->map(fn ($i) => $i->zonaEn($pedido->idioma))->join(', ');
            $lineas[] = [
                'descripcion' => trim($linea->nombreEn($pedido->idioma).($linea->colorEn($pedido->idioma) ? ' · '.$linea->colorEn($pedido->idioma) : '')
                    .($tallas !== '' ? ' ('.$tallas.')' : '').($zonas !== '' ? ' · '.__('facturas.impresion', ['zonas' => $zonas], $pedido->idioma) : '')
                    .($linea->descuento + $linea->cupon > 0 ? ' · '.__('facturas.descuento', ['importe' => Formato::euros($linea->descuento + $linea->cupon, $pedido->idioma)], $pedido->idioma) : '')),
                'cantidad' => $linea->unidades,
                'precio_unitario' => intdiv($linea->neto * 2 + $linea->unidades, $linea->unidades * 2),
                'tipo_iva' => $linea->tipo_iva,
                'base' => $linea->neto,
            ];
        }
        if ($pedido->envio > 0) {
            $lineas[] = ['descripcion' => __('facturas.envio', [], $pedido->idioma), 'cantidad' => 1, 'precio_unitario' => $pedido->envio, 'tipo_iva' => null, 'base' => $pedido->envio];
        }
        $desglose = array_map(fn ($i) => array_map('intval', $i), $pedido->desglose['pasos'][5]['detalle'] ?? []);

        return $this->escribir($pedido, $tipo, $lineas, $desglose);
    }

    /**
     * Rectificativa de lo que queda al cancelar (D175): del total si no hubo reembolsos parciales y,
     * si los hubo, del resto (D178). Una sola por factura; si ya no queda nada, ninguna.
     */
    public function rectificativa(Pedido $pedido, string $motivo): ?Factura
    {
        // Bajo el bloqueo del pedido, como los reembolsos parciales: nunca se rectifica dos veces lo mismo.
        return DB::transaction(fn () => $this->rectificarResto(Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail(), $motivo));
    }

    private function rectificarResto(Pedido $pedido, string $motivo): ?Factura
    {
        $original = $this->original($pedido);
        if ($original === null) {
            return null; // nunca se facturó: no hay nada que rectificar
        }
        if (($ya = Factura::where('rectifica_id', $original->id)->where('parcial', false)->first()) !== null) {
            return $ya;
        }
        $parciales = Factura::where('rectifica_id', $original->id)->where('parcial', true)->orderBy('id')->get();
        $pendiente = RepartoReembolso::pendiente($original->desglose, $parciales->map(fn (Factura $f) => $f->desglose)->all());
        if ($pendiente === []) {
            return null; // ya se devolvió todo con reembolsos parciales
        }
        $tipo = $original->tipo === TipoFactura::Completa ? TipoFactura::Rectificativa : TipoFactura::RectificativaSimplificada;
        $lineas = $parciales->isEmpty()
            ? $original->lineas->map(fn ($l) => ['descripcion' => $l->descripcion, 'cantidad' => -$l->cantidad, 'precio_unitario' => $l->precio_unitario,
                'tipo_iva' => $l->tipo_iva, 'base' => -$l->base])->all()
            : array_map(fn (array $g) => ['descripcion' => __('facturas.anulacion_resto', [], $pedido->idioma), 'cantidad' => -1, 'precio_unitario' => $g['base'],
                'tipo_iva' => $g['tipo_iva'], 'base' => -$g['base']], $pendiente);
        $desglose = array_map(fn ($d) => [...$d, 'base' => -$d['base'], 'cuota' => -$d['cuota'], 'cuota_recargo' => -$d['cuota_recargo']], $pendiente);

        return $this->escribir($pedido, $tipo, $lineas, $desglose, $original, $motivo);
    }

    /**
     * Rectificativa parcial por un reembolso (incidencias, paso 6.6b, D178): el importe con impuestos
     * se reparte entre los tipos de IVA en proporción a lo que queda por rectificar.
     */
    public function rectificativaParcial(Pedido $pedido, int $importe, string $concepto, string $motivo): Factura
    {
        return DB::transaction(function () use ($pedido, $importe, $concepto, $motivo) {
            // El pedido bloqueado: dos reembolsos a la vez no se pasan de lo cobrado.
            $pedido = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            $original = $this->original($pedido) ?? throw new LogicException("El pedido {$pedido->numero} no tiene factura que rectificar.");
            if (Factura::where('rectifica_id', $original->id)->where('parcial', false)->exists()) {
                throw new LogicException("El pedido {$pedido->numero} ya está rectificado entero.");
            }
            $rectificadas = Factura::where('rectifica_id', $original->id)->where('parcial', true)->pluck('desglose')->all();
            $reparto = RepartoReembolso::repartir(RepartoReembolso::pendiente($original->desglose, $rectificadas), $importe);

            $tipo = $original->tipo === TipoFactura::Completa ? TipoFactura::Rectificativa : TipoFactura::RectificativaSimplificada;
            $lineas = array_map(fn (array $g) => ['descripcion' => $concepto, 'cantidad' => -1, 'precio_unitario' => $g['base'], 'tipo_iva' => $g['tipo_iva'], 'base' => -$g['base']], $reparto);
            $desglose = array_map(fn ($d) => [...$d, 'base' => -$d['base'], 'cuota' => -$d['cuota'], 'cuota_recargo' => -$d['cuota_recargo']], $reparto);

            return $this->escribir($pedido, $tipo, $lineas, $desglose, $original, $motivo, parcial: true);
        });
    }

    /** Lo que queda por devolver de un pedido facturado, con impuestos (0 si no hay factura o ya está rectificado entero). */
    public function pendienteDeRectificar(Pedido $pedido): int
    {
        $original = $this->original($pedido);
        if ($original === null || Factura::where('rectifica_id', $original->id)->where('parcial', false)->exists()) {
            return 0;
        }
        $pendiente = RepartoReembolso::pendiente($original->desglose, Factura::where('rectifica_id', $original->id)->where('parcial', true)->pluck('desglose')->all());

        return array_sum(array_map(fn (array $g) => $g['base'] + $g['cuota'] + $g['cuota_recargo'], $pendiente));
    }

    private function original(Pedido $pedido): ?Factura
    {
        return Factura::with('lineas')->where('pedido_id', $pedido->id)->whereIn('tipo', [TipoFactura::Completa->value, TipoFactura::Simplificada->value])->first();
    }

    /**
     * @param  list<array{descripcion: string, cantidad: int, precio_unitario: int, tipo_iva: ?int, base: int}>  $lineas
     * @param  list<array{tipo_iva: int, recargo: int, base: int, cuota: int, cuota_recargo: int}>  $desglose
     */
    private function escribir(Pedido $pedido, TipoFactura $tipo, array $lineas, array $desglose, ?Factura $rectifica = null, ?string $motivo = null, bool $parcial = false): Factura
    {
        if (($falta = Emisor::falta()) !== []) {
            throw new EmisorIncompleto('Faltan datos fiscales de la tienda: '.implode(', ', $falta).'.');
        }
        $base = array_sum(array_column($desglose, 'base'));
        $iva = array_sum(array_column($desglose, 'cuota'));
        $recargo = array_sum(array_column($desglose, 'cuota_recargo'));
        // La factura cuadra con el pedido; una rectificativa nunca devuelve más de lo facturado.
        if ($rectifica === null ? $base + $iva + $recargo !== $pedido->total : $base + $iva + $recargo >= 0 || -($base + $iva + $recargo) > $rectifica->total) {
            throw new LogicException("La factura del pedido {$pedido->numero} no cuadra con su total.");
        }

        return DB::transaction(function () use ($pedido, $tipo, $lineas, $desglose, $rectifica, $motivo, $base, $iva, $recargo, $parcial) {
            // Primero el contador de la cadena: así las facturas se encadenan de una en una (sin huecos ni dos con la misma huella anterior).
            Numeracion::siguiente('facturas');
            $anterior = Factura::latest('id')->first();
            $ahora = now()->timezone('Europe/Madrid');
            $serie = Emisor::serie($tipo);
            $anio = (int) $ahora->format('Y');
            $numero = Numeracion::siguiente("factura:{$serie}:{$anio}");
            $codigo = sprintf('%s%d-%06d', $serie, $anio, $numero);
            $emisor = Emisor::datos();
            $fecha = $ahora->format('d-m-Y');
            $generado = $ahora->toIso8601String();
            $total = $base + $iva + $recargo;
            $activo = (bool) config('facturacion.verifactu.activo');
            $huella = Verifactu::huella((string) $emisor['nif'], $codigo, $fecha, $tipo->value, $iva + $recargo, $total, $anterior?->huella, $generado);

            $factura = Factura::create([
                'uuid' => (string) Str::uuid(),
                'serie' => $serie, 'anio' => $anio, 'numero' => $numero, 'codigo' => $codigo, 'tipo' => $tipo,
                'pedido_id' => $pedido->id, 'rectifica_id' => $rectifica?->id, 'parcial' => $parcial, 'motivo' => $motivo === null ? null : mb_substr($motivo, 0, 255),
                'emitida_en' => $ahora, 'idioma' => $pedido->idioma, 'emisor' => $emisor,
                'destinatario' => ['nombre' => $pedido->nombre_fiscal, 'nif' => $pedido->nif, 'direccion' => $pedido->direccion_facturacion, 'correo' => $pedido->correo],
                'base_imponible' => $base, 'iva' => $iva, 'recargo' => $recargo, 'total' => $total, 'desglose' => $desglose,
                'verifactu' => $activo, 'huella' => $huella, 'huella_anterior' => $anterior?->huella, 'registro_generado_en' => $generado,
                'qr_url' => Verifactu::qr((bool) config('facturacion.verifactu.produccion'), (string) $emisor['nif'], $codigo, $fecha, $total),
                'aeat_estado' => $activo ? 'pendiente' : 'desactivado',
            ]);
            foreach ($lineas as $i => $linea) {
                $factura->lineas()->create([...$linea, 'orden' => $i, 'descripcion' => mb_substr($linea['descripcion'], 0, 255)]);
            }
            $pedido->historial()->create(['estado' => $pedido->estado, 'nota' => ($tipo->esRectificativa() ? 'Rectificativa ' : 'Factura ').$codigo.' emitida']);

            return $factura;
        });
    }
}
