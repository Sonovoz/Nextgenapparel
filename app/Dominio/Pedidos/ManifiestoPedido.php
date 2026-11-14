<?php

namespace App\Dominio\Pedidos;

use App\Dominio\Clientes\Paises;
use App\Dominio\Produccion\OrdenTrabajo;
use App\Models\Color;
use App\Models\ImpresionPedido;
use App\Models\LineaPack;
use App\Models\LineaPedido;
use App\Models\Pedido;
use App\Models\Prenda;
use App\Models\Talla;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * D241: el manifiesto de un pedido. Qué prendas, de qué color y en qué tallas (también las de cada pack), y las
 * referencias de impresión de cada zona (archivo, medida en cm y posición). Se ve en la ficha del pedido y se
 * descarga en un ZIP con los diseños tal como se cerró el pedido (sin modificar) y un PDF con los datos del cliente.
 */
final class ManifiestoPedido
{
    /**
     * Una fila por prenda y color: las líneas sueltas y cada elección de los packs.
     *
     * @return list<array{linea: int, prenda: string, referencia: ?string, color: string, hex: ?string, coleccion: ?string, pack: ?string, tallas: array<string, int>, unidades: int}>
     */
    public static function prendas(Pedido $pedido): array
    {
        $pedido->loadMissing('lineas');
        $filas = [];
        foreach ($pedido->lineas->values() as $i => $linea) {
            if ($linea->tipo !== 'pack') {
                $tallas = [];
                foreach ($linea->tallas ?? [] as $t) {
                    $tallas[(string) $t['nombre']] = ($tallas[(string) $t['nombre']] ?? 0) + (int) $t['unidades'];
                }
                $filas[] = [
                    'linea' => $i + 1, 'prenda' => $linea->nombreEn('es'), 'referencia' => self::referencia($linea->prenda_id),
                    'color' => (string) $linea->colorEn('es'), 'hex' => $linea->color_hex, 'coleccion' => $linea->coleccion, 'pack' => null,
                    'tallas' => $tallas, 'unidades' => array_sum($tallas) ?: $linea->unidades,
                ];

                continue;
            }
            foreach ((array) $linea->elecciones as $lineaPackId => $eleccion) {
                $prendaId = isset($eleccion['prenda']) ? (int) $eleccion['prenda'] : LineaPack::find($lineaPackId)?->prenda_id;
                $color = isset($eleccion['color']) ? Color::find((int) $eleccion['color']) : null;
                $tallas = [];
                foreach ((array) ($eleccion['tallas'] ?? []) as $tallaId => $unidades) {
                    if ((int) $unidades > 0) {
                        $nombre = (string) (Talla::find((int) $tallaId)->nombre ?? $tallaId);
                        $tallas[$nombre] = ($tallas[$nombre] ?? 0) + (int) $unidades;
                    }
                }
                $prenda = $prendaId !== null ? Prenda::find($prendaId) : null;
                $filas[] = [
                    'linea' => $i + 1, 'prenda' => $prenda !== null ? (string) $prenda->getTranslation('nombre', 'es') : 'Prenda del pack',
                    'referencia' => $prenda?->referencia_proveedor, 'color' => $color !== null ? (string) $color->getTranslation('nombre', 'es') : '—',
                    'hex' => $color?->codigo_hex, 'coleccion' => $linea->coleccion, 'pack' => $linea->nombreEn('es'),
                    'tallas' => $tallas, 'unidades' => array_sum($tallas),
                ];
            }
        }

        return $filas;
    }

    /**
     * Las tallas que salen en el pedido, en el orden de Tallas (S, M, L…), con el total de cada una.
     *
     * @param  list<array{tallas: array<string, int>}>  $filas
     * @return array<string, int>
     */
    public static function tallas(array $filas): array
    {
        $totales = [];
        foreach ($filas as $fila) {
            foreach ($fila['tallas'] as $talla => $unidades) {
                $totales[$talla] = ($totales[$talla] ?? 0) + $unidades;
            }
        }
        $orden = Talla::whereIn('nombre', array_keys($totales))->pluck('orden', 'nombre');
        uksort($totales, fn ($a, $b) => [$orden[$a] ?? 999, $a] <=> [$orden[$b] ?? 999, $b]);

        return $totales;
    }

    /**
     * Las referencias de impresión de cada línea: zona, archivo (con su nombre en el ZIP), medida en cm y posición.
     *
     * @return list<array{linea: int, zona: string, cara: ?string, archivo: ?string, original: ?string, borrado: bool, texto: ?string, medida: string, posicion: string, calidad: ?string}>
     */
    public static function impresiones(Pedido $pedido): array
    {
        $pedido->loadMissing('lineas.impresiones');
        $filas = [];
        foreach ($pedido->lineas->values() as $i => $linea) {
            foreach ($linea->impresiones as $impresion) {
                $filas[] = [
                    'linea' => $i + 1,
                    'zona' => $impresion->zonaEn('es'),
                    'cara' => $impresion->cara,
                    'archivo' => $impresion->texto === null ? self::nombreDiseno($pedido, $i + 1, $impresion) : null,
                    'original' => $impresion->nombre_original,
                    'borrado' => $impresion->texto === null && $impresion->archivo === null,
                    'texto' => $impresion->texto,
                    'medida' => self::cm($impresion->ancho_mm).' × '.self::cm($impresion->alto_mm).' cm',
                    'posicion' => self::cm($impresion->desde_cuello_mm).' cm bajo el cuello · '.($impresion->desde_centro_mm === 0 ? 'centrado'
                        : self::cm(abs($impresion->desde_centro_mm)).' cm a la '.($impresion->desde_centro_mm < 0 ? 'izquierda' : 'derecha').' de quien la lleva'),
                    'calidad' => $impresion->ppp ? $impresion->ppp.' ppp ('.$impresion->nivel_calidad.')' : null,
                ];
            }
        }

        return $filas;
    }

    /** PED-0004_L1_frontal_16,3x30cm.png: pedido, línea, zona y medida de impresión. */
    public static function nombreDiseno(Pedido $pedido, int $numeroLinea, ImpresionPedido $impresion): string
    {
        $extension = strtolower(pathinfo((string) ($impresion->archivo ?? $impresion->nombre_original ?? ''), PATHINFO_EXTENSION)) ?: 'png';

        return $pedido->numero.'_L'.$numeroLinea.'_'.Str::slug($impresion->zonaEn('es')).'_'.self::cm($impresion->ancho_mm).'x'.self::cm($impresion->alto_mm).'cm.'.$extension;
    }

    public static function pdf(Pedido $pedido): string
    {
        $pedido->loadMissing('lineas.impresiones', 'lineas.maquetas');
        $filas = self::prendas($pedido);
        $envio = $pedido->direccion_envio;

        return Pdf::loadView('pdf.manifiesto', [
            'pedido' => $pedido,
            'filas' => $filas,
            'tallas' => self::tallas($filas),
            'impresiones' => self::impresiones($pedido),
            'direccion' => implode(', ', array_filter([
                $envio['nombre'] ?? null, $envio['empresa'] ?? null, $envio['direccion'] ?? null, $envio['direccion_2'] ?? null,
                trim(($envio['codigo_postal'] ?? '').' '.($envio['localidad'] ?? '')), $envio['provincia'] ?? null,
                Paises::disponibles()[$envio['pais'] ?? ''] ?? ($envio['pais'] ?? null),
            ])),
            'maquetas' => $pedido->lineas->values()->mapWithKeys(fn (LineaPedido $l, int $i) => [$i + 1 => $l->maquetas->map(fn ($m) => OrdenTrabajo::imagen($m->ruta))->filter()->values()->all()])->all(),
        ])->setPaper('a4')->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true])->output();
    }

    /** El ZIP: los diseños tal cual se cerró el pedido (sin tocar un byte), las maquetas y el PDF. Quien lo pide lo borra. */
    public static function zip(Pedido $pedido): string
    {
        $pedido->loadMissing('lineas.impresiones', 'lineas.maquetas');
        $ruta = tempnam(sys_get_temp_dir(), 'manifiesto-').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se ha podido crear el manifiesto.');
        }
        $disco = Storage::disk(Pedido::DISCO);
        foreach ($pedido->lineas->values() as $i => $linea) {
            foreach ($linea->impresiones as $impresion) {
                if ($impresion->archivo !== null && $disco->exists($impresion->archivo)) {
                    $zip->addFromString('disenos/'.self::nombreDiseno($pedido, $i + 1, $impresion), (string) $disco->get($impresion->archivo));
                }
            }
            foreach ($linea->maquetas as $maqueta) {
                if ($disco->exists($maqueta->ruta)) {
                    $zip->addFromString('maquetas/'.$pedido->numero.'_L'.($i + 1).'_'.$maqueta->cara.'.webp', (string) $disco->get($maqueta->ruta));
                }
            }
        }
        $zip->addFromString($pedido->numero.'_manifiesto.pdf', self::pdf($pedido));
        $zip->close();

        return $ruta;
    }

    public static function cm(int $mm): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format($mm / 10, 1, '.', ''), '0'), '.'));
    }

    private static function referencia(?int $prendaId): ?string
    {
        return $prendaId === null ? null : Prenda::whereKey($prendaId)->value('referencia_proveedor');
    }
}
