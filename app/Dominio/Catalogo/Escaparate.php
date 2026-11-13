<?php

namespace App\Dominio\Catalogo;

use App\Dominio\Precios\Formato;
use App\Dominio\Precios\Impuestos;
use App\Dominio\Precios\Iva;
use App\Models\Color;
use App\Models\EntradaBlog;
use App\Models\Familia;
use App\Models\Foto;
use App\Models\Prenda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Lo que ve el cliente en la tienda: familias y prendas activas, con sus
 * fotos, precio «desde» con IVA y zonas. Solo lectura.
 */
class Escaparate
{
    /**
     * Busca por la URL en el idioma; si el inglés no tiene URL propia, vale la española.
     *
     * @template TModelo of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModelo>  $consulta
     * @return Builder<TModelo>
     */
    public static function porSlug(Builder $consulta, string $slug, string $idioma): Builder
    {
        return $consulta->where(fn (Builder $q) => $q
            ->where("slug->{$idioma}", $slug)
            ->when($idioma !== 'es', fn (Builder $q) => $q->orWhere(fn (Builder $q) => $q->whereNull("slug->{$idioma}")->where('slug->es', $slug))));
    }

    /** @return Collection<int, Familia> */
    public static function familias(): Collection
    {
        return Familia::where('activa', true)->orderBy('orden')->get();
    }

    public static function familia(string $slug, string $idioma): Familia
    {
        return self::porSlug(Familia::where('activa', true), $slug, $idioma)->firstOrFail();
    }

    /** @return Collection<int, Prenda> */
    public static function prendas(Familia $familia): Collection
    {
        return $familia->prendas()->where('activa', true)
            ->whereHas('variantes', fn ($q) => $q->aLaVenta())
            ->with('fotos')->orderBy('id')->get();
    }

    public static function prenda(Familia $familia, string $slug, string $idioma): Prenda
    {
        return self::porSlug($familia->prendas()->where('activa', true)->getQuery(), $slug, $idioma)
            ->whereHas('variantes', fn ($q) => $q->aLaVenta())
            ->firstOrFail();
    }

    public static function slug(Familia|Prenda|EntradaBlog $modelo, string $idioma): string
    {
        return $modelo->getTranslation('slug', $idioma, false) ?: $modelo->getTranslation('slug', 'es');
    }

    /**
     * Precio unitario más bajo al que se vende, sin IVA y en céntimos: la
     * variación a la venta más barata + la zona activa más barata (D118). La
     * prenda nunca se vende sin imprimir. Null si no hay variación o zona.
     */
    public static function desdeSinIva(Prenda $prenda): ?int
    {
        $prendaLisa = $prenda->precioDesde();
        $zonas = self::preciosZonas($prenda);

        return $prendaLisa === null || $zonas === [] ? null : $prendaLisa + min($zonas);
    }

    /** El más alto con una zona: variación más cara + zona más cara, sin IVA. */
    public static function hastaSinIva(Prenda $prenda): ?int
    {
        $maximo = $prenda->variantes()->aLaVenta()->max('precio_base');
        $zonas = self::preciosZonas($prenda);

        return $maximo === null || $zonas === [] ? null : (int) $maximo + max($zonas);
    }

    /** @return list<int> precio de cada zona activa de la prenda */
    private static function preciosZonas(Prenda $prenda): array
    {
        return $prenda->zonas()->where('zonas_impresion.activa', true)->pluck('prenda_zona.precio')->map(fn ($p) => (int) $p)->all();
    }

    /** «Desde 7,26 €» con IVA (prenda + impresión más barata), o null si no se vende. */
    public static function precioDesde(Prenda $prenda): ?string
    {
        $sinIva = self::desdeSinIva($prenda);

        return $sinIva === null ? null : Formato::euros(Iva::conIva($sinIva, Impuestos::tipoIva($prenda->clase_impuesto_id)));
    }

    /** Foto principal: maqueta delantera de un color a la venta; si no, la primera foto que no sea de un color retirado. */
    public static function fotoPrincipal(Prenda $prenda): ?Foto
    {
        $aLaVenta = $prenda->variantes()->aLaVenta()->distinct()->pluck('color_id')->all();
        $fotos = $prenda->fotos->filter(fn (Foto $f) => $f->color_id === null || in_array($f->color_id, $aLaVenta, true))
            ->sortBy(fn (Foto $f) => array_search($f->color_id, $aLaVenta, true));

        return $fotos->first(fn (Foto $f) => $f->tipo === TipoFoto::Maqueta && $f->cara === Cara::Delantera)
            ?? $fotos->first();
    }

    /**
     * Datos de la ficha para el visor de colores y caras.
     *
     * @return list<array<string, mixed>>
     */
    public static function colores(Prenda $prenda): array
    {
        $idsColores = $prenda->variantes()->aLaVenta()->distinct()->pluck('color_id');
        // Solo las zonas que la prenda ofrece ahora (con su precio) y activas.
        $ofrecidas = $prenda->zonas()->where('zonas_impresion.activa', true)->get();
        $zonasOfrecidas = $ofrecidas->pluck('id')->all();
        $tipo = Impuestos::tipoIva($prenda->clase_impuesto_id);
        // Lo que suma cada zona por prenda, con IVA (calculado aquí, nunca en el navegador).
        $precioZona = $ofrecidas->mapWithKeys(fn ($z) => [$z->id => Formato::euros(Iva::conIva((int) $z->pivot->precio, $tipo))]);
        $maquetas = $prenda->fotos()->where('tipo', TipoFoto::Maqueta->value)
            ->with(['zonas' => fn ($q) => $q->whereIn('zona_impresion_id', $zonasOfrecidas), 'zonas.zona'])->get();

        // Tallas a la venta de cada color, para elegir cantidades en el configurador.
        $tallas = $prenda->variantes()->aLaVenta()->with('talla')->get()->groupBy('color_id')
            ->map(fn ($vs) => $vs->sortBy(fn ($v) => $v->talla->orden)->map(fn ($v) => ['id' => $v->talla_id, 'nombre' => $v->talla->nombre])->values()->all());

        return Color::whereIn('id', $idsColores)->orderBy('orden')->get()->map(function (Color $color) use ($maquetas, $precioZona, $tallas): array {
            $caras = [];

            foreach (Cara::cases() as $cara) {
                $foto = $maquetas->first(fn (Foto $f) => $f->color_id === $color->id && $f->cara === $cara);
                $caras[$cara->value] = $foto === null ? null : [
                    'url' => $foto->url(),
                    'alt' => $foto->texto_alternativo ?: null,
                    'zonas' => $foto->zonas->map(fn ($z) => [
                        'precio' => $precioZona[$z->zona_impresion_id] ?? null,
                        'id' => $z->zona_impresion_id, 'nombre' => $z->zona?->nombre, 'clave' => $z->zona?->clave,
                        'ancho_mm' => $z->zona?->ancho_max_mm, 'alto_mm' => $z->zona?->alto_max_mm,
                        // Dónde está la zona en la prenda (D99): el carrito guarda la posición en mm.
                        'cuello_mm' => $z->zona?->desde_cuello_mm, 'centro_mm' => $z->zona?->desde_centro_mm,
                        'x' => $z->x / 100, 'y' => $z->y / 100, 'ancho' => $z->ancho / 100, 'alto' => $z->alto / 100,
                    ])->values()->all(),
                ];
            }

            return ['id' => $color->id, 'nombre' => $color->nombre, 'hex' => $color->codigo_hex, 'caras' => $caras, 'tallas' => $tallas[$color->id] ?? []];
        })->values()->all();
    }
}
