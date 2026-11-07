<?php

namespace App\Dominio\Catalogo;

use App\Dominio\Precios\Formato;
use App\Dominio\Precios\Iva;
use App\Models\Color;
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
    /** Busca por la URL en el idioma; si el inglés no tiene URL propia, vale la española. */
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

    public static function slug(Familia|Prenda $modelo, string $idioma): string
    {
        return $modelo->getTranslation('slug', $idioma, false) ?: $modelo->getTranslation('slug', 'es');
    }

    /** «Desde 5,08 €» con IVA, o null si no tiene precio. */
    public static function precioDesde(Prenda $prenda): ?string
    {
        $sinIva = $prenda->precioDesde();

        return $sinIva === null ? null : Formato::euros(Iva::conIva($sinIva));
    }

    /** Foto principal: maqueta delantera del primer color; si no, la primera foto. */
    public static function fotoPrincipal(Prenda $prenda): ?Foto
    {
        $fotos = $prenda->fotos;

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
        $zonasOfrecidas = $prenda->zonas()->where('zonas_impresion.activa', true)->pluck('zonas_impresion.id')->all();
        $maquetas = $prenda->fotos()->where('tipo', TipoFoto::Maqueta->value)
            ->with(['zonas' => fn ($q) => $q->whereIn('zona_impresion_id', $zonasOfrecidas), 'zonas.zona'])->get();

        return Color::whereIn('id', $idsColores)->orderBy('orden')->get()->map(function (Color $color) use ($maquetas): array {
            $caras = [];

            foreach (Cara::cases() as $cara) {
                $foto = $maquetas->first(fn (Foto $f) => $f->color_id === $color->id && $f->cara === $cara);
                $caras[$cara->value] = $foto === null ? null : [
                    'url' => $foto->url(),
                    'alt' => $foto->texto_alternativo ?: null,
                    'zonas' => $foto->zonas->map(fn ($z) => [
                        'id' => $z->zona_impresion_id, 'nombre' => $z->zona?->nombre,
                        'ancho_mm' => $z->zona?->ancho_max_mm, 'alto_mm' => $z->zona?->alto_max_mm,
                        'x' => $z->x / 100, 'y' => $z->y / 100, 'ancho' => $z->ancho / 100, 'alto' => $z->alto / 100,
                    ])->values()->all(),
                ];
            }

            return ['id' => $color->id, 'nombre' => $color->nombre, 'hex' => $color->codigo_hex, 'caras' => $caras];
        })->values()->all();
    }
}
