<?php

namespace App\Dominio\Asistente;

use App\Dominio\Catalogo\Escaparate;
use App\Dominio\Precios\Configurador;
use App\Dominio\Precios\Formato;
use App\Dominio\Precios\Impuestos;
use App\Dominio\Precios\Iva;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\Variante;

/**
 * Lo que el asistente puede consultar (v2, D194): el catálogo, el **precio que calcula el motor** y los packs. La IA
 * nunca calcula precios (regla 3): solo los pide aquí y los explica.
 */
final class HerramientasVenta
{
    public function __construct(private readonly Configurador $configurador) {}

    /** @return list<array<string, mixed>> definición para OpenAI */
    public static function definiciones(): array
    {
        return [
            ['type' => 'function', 'function' => ['name' => 'catalogo', 'description' => 'Prendas a la venta con sus ids, colores, tallas, zonas de impresión y precio desde (con IVA).',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass]]],
            ['type' => 'function', 'function' => ['name' => 'calcular_precio', 'description' => 'Precio exacto con IVA (sin envío) que calcula la tienda para una prenda, un color, sus zonas y unidades por talla. Incluye avisos de mínimos y ofertas.',
                'parameters' => ['type' => 'object', 'required' => ['prenda_id', 'color_id', 'zonas', 'tallas'], 'properties' => [
                    'prenda_id' => ['type' => 'integer'], 'color_id' => ['type' => 'integer'],
                    'zonas' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'ids de las zonas con diseño'],
                    'tallas' => ['type' => 'object', 'description' => 'talla_id => unidades', 'additionalProperties' => ['type' => 'integer']],
                ]]]],
            ['type' => 'function', 'function' => ['name' => 'packs', 'description' => 'Packs a la venta con su precio cerrado (con IVA) y lo que incluyen.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass]]],
        ];
    }

    /** @param  array<string, mixed>  $argumentos */
    public function ejecutar(string $nombre, array $argumentos, string $idioma): mixed
    {
        return match ($nombre) {
            'catalogo' => $this->catalogo($idioma),
            'calcular_precio' => $this->precio($argumentos, $idioma),
            'packs' => $this->packs($idioma),
            default => ['error' => 'Herramienta desconocida'],
        };
    }

    /** @return list<array<string, mixed>> */
    private function catalogo(string $i): array
    {
        return Prenda::where('activa', true)->with('familia', 'zonas')->orderBy('id')->get()->map(function (Prenda $p) use ($i) {
            $variantes = Variante::aLaVenta()->where('prenda_id', $p->id)->with('color', 'talla')->get();

            return [
                'prenda_id' => $p->id, 'nombre' => $p->getTranslation('nombre', $i), 'familia' => $p->familia?->getTranslation('nombre', $i),
                'precio_desde' => Escaparate::precioDesde($p),
                'colores' => $variantes->pluck('color')->filter()->unique('id')->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->getTranslation('nombre', $i)])->values()->all(),
                'tallas' => $variantes->pluck('talla')->filter()->unique('id')->sortBy('orden')->map(fn ($t) => ['id' => $t->id, 'nombre' => $t->nombre])->values()->all(),
                'zonas' => $p->zonas->where('activa', true)->map(fn ($z) => ['id' => $z->id, 'nombre' => $z->getTranslation('nombre', $i), 'max_cm' => ($z->ancho_max_mm / 10).' × '.($z->alto_max_mm / 10)])->values()->all(),
            ];
        })->all();
    }

    /** @param  array<string, mixed>  $a */
    private function precio(array $a, string $i): array
    {
        $prenda = Prenda::where('activa', true)->find((int) ($a['prenda_id'] ?? 0));
        if ($prenda === null) {
            return ['error' => 'Esa prenda no está a la venta.'];
        }
        $tallas = [];
        foreach ((array) ($a['tallas'] ?? []) as $talla => $unidades) {
            if ((int) $unidades > 0) {
                $tallas[(int) $talla] = min((int) $unidades, 5000);
            }
        }
        $color = (int) ($a['color_id'] ?? 0);
        $r = $this->configurador->calcular($prenda, $color, array_map('intval', (array) ($a['zonas'] ?? [])), $tallas === [] ? [] : [$color => $tallas]);

        return [
            'total_con_iva_sin_envio' => $r['total'] === null ? null : Formato::euros($r['total'], $i),
            'por_unidad_con_iva' => $r['por_unidad'] === null ? null : Formato::euros($r['por_unidad'], $i),
            'unidades' => $r['unidades'], 'se_puede_pedir' => $r['confirmable'],
            'avisos' => $r['avisos'], 'impedimentos' => $r['impedimentos'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function packs(string $i): array
    {
        return Pack::where('activo', true)->with('lineas.prenda')->get()->map(fn (Pack $p) => [
            'nombre' => $p->getTranslation('nombre', $i),
            'precio_con_iva' => Formato::euros(Iva::conIva($p->precio, Impuestos::tipoIva($p->clase_impuesto_id)), $i),
            'incluye' => $p->lineas->map(fn ($l) => $l->unidades.' × '.$l->prenda?->getTranslation('nombre', $i))->all(),
        ])->all();
    }
}
