<?php

namespace App\Dominio\Catalogo\Proveedores;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\Origen;
use App\Dominio\Precios\PrecioConMargen;
use App\Jobs\ImportarFotosTopTex;
use App\Models\Color;
use App\Models\Familia;
use App\Models\Prenda;
use App\Models\Proveedor;
use App\Models\Talla;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * D254: importa una referencia de TopTex (p. ej. GI6400B) como prenda con sus variaciones color × talla.
 * - La primera vez crea la prenda (oculta en la tienda hasta que el equipo le ponga fotos y zonas) con nombre,
 *   descripción, composición, gramaje y argumentos de venta. Después ya no toca esos textos: son del equipo.
 * - Cada vez crea los colores y tallas nuevos y pone en cada variación el SKU de TopTex, el coste (tramo de
 *   1 unidad), el precio de venta = coste + margen (PrecioConMargen) y el peso. Las que TopTex retira se
 *   desactivan; nunca se borran. Una variación sin precio o sin peso en TopTex no se crea (no se vende a 0 €).
 */
final class ImportarTopTex
{
    public const PROVEEDOR = 'TopTex';

    public function __construct(private readonly ClienteTopTex $toptex) {}

    /**
     * @return array{prenda: Prenda, creada: bool, colores: int, variantes_nuevas: int, variantes_actualizadas: int, desactivadas: int, sin_precio: int, fotos: int, fotos_bloqueadas: bool}
     */
    public function __invoke(string $referencia, ?Familia $familia = null): array
    {
        $referencia = Str::upper(trim($referencia));
        $ficha = $this->toptex->producto($referencia);
        if ($ficha === null) {
            throw new ProveedorNoDisponible("TopTex no tiene la referencia {$referencia}.");
        }
        $costes = $this->toptex->costes($referencia);

        return DB::transaction(function () use ($referencia, $ficha, $costes, $familia): array {
            $proveedor = Proveedor::firstOrCreate(['nombre' => self::PROVEEDOR], ['tipo_conector' => Origen::Api, 'activo' => true]);
            $prenda = Prenda::where('proveedor_id', $proveedor->getKey())->where('referencia_proveedor', $referencia)->first();
            $creada = $prenda === null;
            if ($prenda === null) {
                if ($familia === null) {
                    throw new ProveedorNoDisponible('Elige la familia de la prenda nueva.');
                }
                $prenda = Prenda::create(self::datosPrenda($ficha, $referencia) + [
                    'familia_id' => $familia->getKey(),
                    'proveedor_id' => $proveedor->getKey(),
                    'referencia_proveedor' => $referencia,
                    'activa' => false,
                ]);
            }

            $margen = PrecioConMargen::margen($prenda);
            $resumen = ['colores' => 0, 'variantes_nuevas' => 0, 'variantes_actualizadas' => 0, 'desactivadas' => 0, 'sin_precio' => 0, 'fotos' => 0, 'fotos_bloqueadas' => false];
            $fotos = [];
            foreach ((array) ($ficha['colors'] ?? []) as $colorTopTex) {
                if (! is_array($colorTopTex)) {
                    continue;
                }
                $color = self::color($colorTopTex);
                if ($color === null) {
                    continue;
                }
                $resumen['colores']++;
                $resumen['fotos_bloqueadas'] = $resumen['fotos_bloqueadas'] || str_contains((string) json_encode($colorTopTex['packshots'] ?? []), 'charter');
                $colorActivo = ($colorTopTex['saleState'] ?? 'active') === 'active';
                foreach (self::fotos((array) ($colorTopTex['packshots'] ?? [])) as $cara => $url) {
                    $fotos[] = ['color_id' => (int) $color->getKey(), 'cara' => $cara, 'url' => $url];
                }

                foreach ((array) ($colorTopTex['sizes'] ?? []) as $talla) {
                    if (! is_array($talla) || ! filled($talla['sku'] ?? null) || ! filled($talla['size'] ?? null)) {
                        continue;
                    }
                    $sku = Str::limit((string) $talla['sku'], 64, '');
                    $activa = $colorActivo && ($talla['saleState'] ?? 'active') === 'active' && ! (int) ($talla['isDiscontinued'] ?? 0);
                    $tallaModelo = Talla::firstOrCreate(['nombre' => Str::limit(trim((string) $talla['size']), 10, '')], ['orden' => (int) ($talla['sortOrder'] ?? 0)]);
                    $variante = $prenda->variantes()->where('sku', $sku)->first()
                        ?? $prenda->variantes()->where('color_id', $color->getKey())->where('talla_id', $tallaModelo->getKey())->first();
                    $coste = $costes[(string) $talla['sku']] ?? null;
                    $gramos = self::gramos($talla, $ficha);

                    if ($coste === null || $coste <= 0 || $gramos === null) {
                        $resumen['sin_precio']++;
                        if ($variante !== null && $variante->activa) {
                            $variante->update(['activa' => false]);
                            $resumen['desactivadas']++;
                        }

                        continue;
                    }

                    $datos = [
                        'sku' => $sku,
                        'coste' => $coste,
                        'precio_base' => PrecioConMargen::venta($coste, $margen),
                        'peso_gramos' => $gramos,
                        'activa' => $activa,
                    ];
                    if ($variante === null) {
                        $prenda->variantes()->create($datos + ['color_id' => $color->getKey(), 'talla_id' => $tallaModelo->getKey(), 'stock' => 0]);
                        $resumen['variantes_nuevas']++;
                    } else {
                        if ($variante->activa && ! $activa) {
                            $resumen['desactivadas']++;
                        }
                        $variante->update($datos);
                        $resumen['variantes_actualizadas']++;
                    }
                }
            }

            if ($fotos !== []) {
                ImportarFotosTopTex::dispatch((int) $prenda->getKey(), $fotos)->afterCommit();
                $resumen['fotos'] = count($fotos);
            }

            return ['prenda' => $prenda, 'creada' => $creada] + $resumen;
        });
    }

    /**
     * Precio de venta = coste + margen en las variaciones de las prendas de proveedor (tras cambiar un margen).
     * Las prendas de alta manual conservan sus precios.
     *
     * @return int variaciones con el precio cambiado
     */
    public static function recalcular(?Prenda $prenda = null): int
    {
        $cambiadas = 0;
        Prenda::query()->whereHas('proveedor', fn ($q) => $q->where('tipo_conector', Origen::Api->value))
            ->when($prenda !== null, fn ($q) => $q->whereKey($prenda->getKey()))
            ->with('variantes')->each(function (Prenda $p) use (&$cambiadas): void {
                $margen = PrecioConMargen::margen($p);
                foreach ($p->variantes as $v) {
                    $precio = PrecioConMargen::venta((int) $v->coste, $margen);
                    if ($v->coste > 0 && $precio !== $v->precio_base) {
                        $v->update(['precio_base' => $precio]);
                        $cambiadas++;
                    }
                }
            });

        return $cambiadas;
    }

    /**
     * @param  array<string, mixed>  $ficha
     * @return array<string, mixed>
     */
    private static function datosPrenda(array $ficha, string $referencia): array
    {
        $nombre = self::texto($ficha['designation'] ?? null) ?: $referencia;
        $argumentos = collect(['salesArguments', 'salesArguments2', 'salesArguments3'])
            ->map(fn ($c) => self::texto($ficha[$c] ?? null))->filter()->implode("\n");
        $marca = trim((string) ($ficha['brand'] ?? ''));
        preg_match('/(\d+)\s*g/', (string) data_get($ficha, 'colors.0.sizes.0.coatingWeight_fr', ''), $g);
        $gramaje = isset($g[1]) && (int) $g[1] >= 50 && (int) $g[1] <= 1000 ? (int) $g[1] : null;

        return [
            'nombre' => ['es' => $marca !== '' && ! str_contains(Str::lower($nombre), Str::lower($marca)) ? $nombre.' '.$marca : $nombre],
            'slug' => ['es' => Str::slug($nombre.' '.$referencia)],
            'descripcion' => self::texto($ficha['description'] ?? null) !== '' ? ['es' => self::texto($ficha['description'])] : null,
            'composicion' => self::texto($ficha['composition'] ?? null) !== '' ? ['es' => self::texto($ficha['composition'])] : null,
            'detalles' => $argumentos !== '' ? ['es' => $argumentos] : null,
            'gramaje' => $gramaje,
        ];
    }

    /**
     * Fotos de un color, de sus «packshots»: delantera (FACE/FRONT), trasera (BACK) y laterales (RIGHT/SIDE y LEFT, D257). Si TopTex da varias para una
     * cara, se prefiere la de maniquí invisible (GHOST). La URL es la primera dirección http de cada entrada.
     *
     * @param  array<mixed>  $packshots
     * @return array<string, string> cara => url
     */
    public static function fotos(array $packshots): array
    {
        $elegidas = [];
        foreach ($packshots as $clave => $entrada) {
            $clave = Str::upper((string) $clave);
            $cara = match (true) {
                str_contains($clave, 'BACK') => Cara::Trasera->value,
                str_contains($clave, 'LEFT') => Cara::Izquierda->value,
                str_contains($clave, 'RIGHT'), str_contains($clave, 'SIDE') => Cara::Derecha->value,
                str_contains($clave, 'FACE'), str_contains($clave, 'FRONT') => Cara::Delantera->value,
                default => null,
            };
            $url = collect(is_array($entrada) ? Arr::flatten($entrada) : [$entrada])
                ->first(fn ($v) => is_string($v) && preg_match('#^https?://#i', $v));
            if ($cara === null || ! is_string($url)) {
                continue;
            }
            $fantasma = str_contains($clave, 'GHOST');
            if (! isset($elegidas[$cara]) || ($fantasma && ! $elegidas[$cara][1])) {
                $elegidas[$cara] = [$url, $fantasma];
            }
        }

        return array_map(fn ($e) => $e[0], $elegidas);
    }

    /** @param  array<string, mixed>  $colorTopTex */
    private static function color(array $colorTopTex): ?Color
    {
        $nombre = self::texto($colorTopTex['colors'] ?? null);
        $hex = '#'.Str::upper(ltrim((string) data_get($colorTopTex, 'colorsHexa.0', ''), '#'));
        if ($nombre === '' || ! preg_match('/^#[0-9A-F]{6}$/', $hex)) {
            return null;
        }

        return Color::where('codigo_hex', $hex)->get()->first(fn (Color $c) => strcasecmp((string) $c->getTranslation('nombre', 'es'), $nombre) === 0)
            ?? Color::create(['nombre' => ['es' => $nombre], 'codigo_hex' => $hex, 'orden' => (int) Color::max('orden') + 1]);
    }

    /**
     * Peso para el envío: el de aduanas de la talla («0,167 kg»); si falta, el medio de la prenda («109 g»).
     *
     * @param  array<string, mixed>  $talla
     * @param  array<string, mixed>  $ficha
     */
    private static function gramos(array $talla, array $ficha): ?int
    {
        foreach ([$talla['customsWeight'] ?? '', $talla['grossBoxWeight'] ?? '', $ficha['averageWeight'] ?? ''] as $peso) {
            if (preg_match('/([\d.,]+)\s*(kg|g)\b/i', (string) $peso, $m)) {
                $valor = (float) str_replace(',', '.', $m[1]);
                $gramos = (int) round(Str::lower($m[2]) === 'kg' ? $valor * 1000 : $valor);
                if ($gramos > 0) {
                    return $gramos;
                }
            }
        }

        return null;
    }

    /** Texto en español de un campo de TopTex ({"es": "…"} o texto). */
    private static function texto(mixed $valor): string
    {
        if (is_array($valor)) {
            $valor = $valor['es'] ?? (array_is_list($valor) ? ($valor[0]['es'] ?? '') : (reset($valor) ?: ''));
        }

        return trim(is_string($valor) ? $valor : '');
    }
}
