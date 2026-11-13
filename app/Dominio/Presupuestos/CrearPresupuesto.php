<?php

namespace App\Dominio\Presupuestos;

use App\Dominio\Carrito\TipoLinea;
use App\Dominio\Carrito\ValorarCarrito;
use App\Dominio\Pedidos\Numeracion;
use App\Models\Ajuste;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\ImpresionCarrito;
use App\Models\LineaCarrito;
use App\Models\Presupuesto;
use App\Models\Talla;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Presupuesto desde el carrito (D187): las líneas con los importes del motor (regla 3), sin envío (se
 * calcula con la dirección), válido los días de Ajustes → General. Solo si el carrito se puede comprar.
 */
final class CrearPresupuesto
{
    public function __construct(private readonly ValorarCarrito $valorar) {}

    public function __invoke(Carrito $carrito, ?Cliente $cliente, string $idioma): ?Presupuesto
    {
        $valoracion = ($this->valorar)($carrito);
        if (! $valoracion->confirmable()) {
            return null;
        }
        $r = $valoracion->resultado;
        $nombresTalla = Talla::pluck('nombre', 'id');
        // El motor da primero las prendas y luego los packs, cada grupo en el orden del carrito.
        $lineas = $carrito->lineas->where('tipo', TipoLinea::Prenda)->values()->concat($carrito->lineas->where('tipo', TipoLinea::Pack)->values());
        $copia = [];
        $origen = [];
        foreach ($lineas as $i => $linea) {
            $calculada = $r->lineas[$i];
            $esPack = $linea->tipo === TipoLinea::Pack;
            $tallas = $esPack ? '' : collect($linea->unidadesPorTalla())->map(fn ($n, $id) => ($nombresTalla[$id] ?? $id).' × '.$n)->join(' · ');
            $copia[] = [
                'descripcion' => trim(($esPack ? $linea->pack?->getTranslation('nombre', $idioma) : $linea->prenda?->getTranslation('nombre', $idioma)).($linea->color ? ' · '.$linea->color->getTranslation('nombre', $idioma) : '')),
                'detalle' => trim($tallas.($linea->impresiones->isEmpty() ? '' : ($tallas === '' ? '' : ' · ').$linea->impresiones->map(fn ($i) => $i->zona?->getTranslation('nombre', $idioma).' '.rtrim(rtrim(number_format($i->ancho_mm / 10, 1, ',', ''), '0'), ',').' × '.rtrim(rtrim(number_format($i->alto_mm / 10, 1, ',', ''), '0'), ',').' cm')->join(', '))),
                'unidades' => $calculada->unidades,
                'neto' => $calculada->neto(),
            ];
            $origen[] = self::origenDe($linea);
        }

        return DB::transaction(fn () => Presupuesto::create([
            'uuid' => (string) Str::uuid(),
            'numero' => 'PRE-'.str_pad((string) Numeracion::siguiente('presupuesto'), 4, '0', STR_PAD_LEFT),
            'cliente_id' => $cliente?->id,
            'idioma' => $idioma,
            'contenido' => ['lineas' => $copia, 'bruto' => $r->bruto(), 'descuentos' => $r->descuentos(), 'descuento_nombre' => $r->oferta['nombre'] ?? $r->cupon['codigo'] ?? null,
                'base' => $r->baseImponible(), 'impuestos' => $r->totalImpuestos(), 'total' => $r->total()],
            'origen' => $origen,
            'total' => $r->total(),
            'valido_hasta' => now()->addHours(max(1, (int) Ajuste::obtener('presupuestos.horas_validez'))),
        ]));
    }

    /**
     * Lo que hace falta para volver a poner la línea en un carrito (PasarAlCarrito) y para reconocerla
     * si ya está en él (HuellaLinea).
     *
     * @return array<string, mixed>
     */
    public static function origenDe(LineaCarrito $linea): array
    {
        return [
            'tipo' => $linea->tipo->value, 'prenda_id' => $linea->prenda_id, 'color_id' => $linea->color_id, 'pack_id' => $linea->pack_id,
            'tallas' => $linea->tipo === TipoLinea::Pack ? null : $linea->unidadesPorTalla(), 'elecciones' => $linea->elecciones,
            'impresiones' => $linea->impresiones->mapWithKeys(fn (ImpresionCarrito $i) => [$i->zona_impresion_id => array_filter([
                'diseno' => $i->diseno?->uuid, 'texto' => $i->texto, 'fuente' => $i->fuente, 'tinta' => $i->tinta,
                'ancho_mm' => $i->ancho_mm, 'alto_mm' => $i->alto_mm, 'desde_cuello_mm' => $i->desde_cuello_mm, 'desde_centro_mm' => $i->desde_centro_mm,
            ], fn ($v) => $v !== null)])->all(),
        ];
    }
}
