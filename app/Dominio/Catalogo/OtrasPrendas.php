<?php

namespace App\Dominio\Catalogo;

use App\Models\Prenda;

/**
 * «Añade otra prenda con este diseño» (D213): prendas para seguir el pedido con el mismo diseño, una de cada
 * familia primero (de la camiseta a la sudadera), con su maqueta y sus zonas para ver el diseño puesto antes
 * de entrar. Solo prendas que se pueden configurar: activas, a la venta y con maqueta con zonas.
 */
final class OtrasPrendas
{
    /**
     * @param  list<int>  $excluir  prendas que no se ofrecen (la que se está configurando, las que ya están en el carrito)
     * @return list<array{id: int, nombre: string, familia: string, url: string, desde: ?string, caras: array<string, array{url: string, zonas: list<array{clave: ?string, x: float, y: float, ancho: float, alto: float}>}|null>}>
     */
    public static function para(array $excluir = [], int $maximo = 4): array
    {
        $idioma = app()->getLocale();
        $prendas = Prenda::where('activa', true)->whereNotIn('id', $excluir)
            ->whereHas('familia', fn ($q) => $q->where('activa', true))
            ->whereHas('variantes', fn ($q) => $q->aLaVenta())
            ->whereHas('fotos', fn ($q) => $q->where('tipo', TipoFoto::Maqueta->value)->whereHas('zonas'))
            ->with('familia')->orderBy('id')->get();
        $familiasExcluidas = Prenda::whereIn('id', $excluir)->pluck('familia_id')->all();

        // Primero una de cada familia que aún no tiene (otro tipo de producto); luego el resto.
        $orden = $prendas->sortBy(fn (Prenda $p) => in_array($p->familia_id, $familiasExcluidas, true) ? 1 : 0)->values();
        $elegidas = $orden->unique('familia_id')->concat($orden)->unique('id')->take($maximo);

        return $elegidas->map(function (Prenda $p) use ($idioma): array {
            $color = Escaparate::colores($p)[0] ?? null;
            $caras = [];
            foreach (Cara::cases() as $cara) {
                $datos = $color['caras'][$cara->value] ?? null;
                $caras[$cara->value] = $datos === null ? null : [
                    'url' => $datos['url'],
                    'zonas' => array_map(fn (array $z) => ['clave' => $z['clave'], 'x' => $z['x'], 'y' => $z['y'], 'ancho' => $z['ancho'], 'alto' => $z['alto']], $datos['zonas']),
                ];
            }

            return [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'familia' => (string) $p->familia?->nombre,
                'url' => route('tienda.prenda', ['idioma' => $idioma, 'familia' => Escaparate::slug($p->familia, $idioma), 'prenda' => Escaparate::slug($p, $idioma)]),
                'desde' => Escaparate::precioDesde($p),
                'caras' => $caras,
            ];
        })->values()->all();
    }

    /**
     * Vista previa de una prenda de para() con los diseños de una línea (DisenosDeLinea): la cara con diseño
     * (delantera si hay alguno delante) y cada diseño en la zona de la misma clave o, si no la tiene, en la
     * primera libre de esa cara. El mismo criterio que la hoja del configurador.
     *
     * @param  array{caras: array<string, array{url: string, zonas: list<array{clave: ?string, x: float, y: float, ancho: float, alto: float}>}|null>}  $prenda
     * @param  list<array<string, mixed>>  $disenos
     * @return array{foto: ?string, capas: list<array{url: string, x: float, y: float, ancho: float, alto: float}>}
     */
    public static function previa(array $prenda, array $disenos): array
    {
        $delante = collect($disenos)->contains(fn ($d) => ($d['cara'] ?? null) === Cara::Delantera->value);
        $cara = $delante || ($prenda['caras'][Cara::Trasera->value] ?? null) === null ? Cara::Delantera->value : Cara::Trasera->value;
        $datos = $prenda['caras'][$cara] ?? null;
        if ($datos === null) {
            return ['foto' => null, 'capas' => []];
        }
        $libres = $datos['zonas'];
        $capas = [];
        foreach ($disenos as $d) {
            if (($d['cara'] ?? null) !== $cara || $libres === []) {
                continue;
            }
            $i = collect($libres)->search(fn ($z) => $z['clave'] !== null && $z['clave'] === ($d['clave'] ?? null));
            $i = $i === false ? array_key_first($libres) : $i;
            $z = $libres[$i];
            unset($libres[$i]);
            $capas[] = ['url' => (string) $d['miniatura'], 'x' => $z['x'], 'y' => $z['y'], 'ancho' => $z['ancho'], 'alto' => $z['alto']];
        }

        return ['foto' => $datos['url'], 'capas' => $capas];
    }
}
