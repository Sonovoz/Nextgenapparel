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
 * - **Rectificativa** por diferencias, en negativo y del total, al cancelar un pedido ya
 *   facturado (R1 de una completa, R5 de una simplificada).
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

    public function rectificativa(Pedido $pedido, string $motivo): ?Factura
    {
        $original = Factura::with('lineas')->where('pedido_id', $pedido->id)->whereIn('tipo', [TipoFactura::Completa->value, TipoFactura::Simplificada->value])->first();
        if ($original === null) {
            return null; // nunca se facturó: no hay nada que rectificar
        }
        if (($ya = Factura::where('rectifica_id', $original->id)->first()) !== null) {
            return $ya;
        }
        $tipo = $original->tipo === TipoFactura::Completa ? TipoFactura::Rectificativa : TipoFactura::RectificativaSimplificada;
        $lineas = $original->lineas->map(fn ($l) => ['descripcion' => $l->descripcion, 'cantidad' => -$l->cantidad, 'precio_unitario' => $l->precio_unitario,
            'tipo_iva' => $l->tipo_iva, 'base' => -$l->base])->all();
        $desglose = array_map(fn ($d) => [...$d, 'base' => -$d['base'], 'cuota' => -$d['cuota'], 'cuota_recargo' => -$d['cuota_recargo']], $original->desglose);

        return $this->escribir($pedido, $tipo, $lineas, $desglose, $original, $motivo);
    }

    /**
     * @param  list<array{descripcion: string, cantidad: int, precio_unitario: int, tipo_iva: ?int, base: int}>  $lineas
     * @param  list<array{tipo_iva: int, recargo: int, base: int, cuota: int, cuota_recargo: int}>  $desglose
     */
    private function escribir(Pedido $pedido, TipoFactura $tipo, array $lineas, array $desglose, ?Factura $rectifica = null, ?string $motivo = null): Factura
    {
        if (($falta = Emisor::falta()) !== []) {
            throw new EmisorIncompleto('Faltan datos fiscales de la tienda: '.implode(', ', $falta).'.');
        }
        $base = array_sum(array_column($desglose, 'base'));
        $iva = array_sum(array_column($desglose, 'cuota'));
        $recargo = array_sum(array_column($desglose, 'cuota_recargo'));
        $esperado = $rectifica === null ? $pedido->total : -$rectifica->total;
        if ($base + $iva + $recargo !== $esperado) {
            throw new LogicException("La factura del pedido {$pedido->numero} no cuadra con su total.");
        }

        return DB::transaction(function () use ($pedido, $tipo, $lineas, $desglose, $rectifica, $motivo, $base, $iva, $recargo) {
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
                'pedido_id' => $pedido->id, 'rectifica_id' => $rectifica?->id, 'motivo' => $motivo === null ? null : mb_substr($motivo, 0, 255),
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
