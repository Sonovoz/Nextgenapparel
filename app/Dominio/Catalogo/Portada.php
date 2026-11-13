<?php

namespace App\Dominio\Catalogo;

use App\Dominio\Precios\Formato;
use App\Dominio\Precios\Impuestos;
use App\Dominio\Precios\Iva;
use App\Models\Familia;
use App\Models\Foto;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\ReglaMinimo;
use App\Models\ZonaImpresion;
use Illuminate\Support\Collection;

/**
 * Lo que muestra la portada, siempre con los datos reales del backend:
 * familias con su «Desde», packs vigentes con su precio con IVA, el mínimo y
 * el plazo de la línea de confianza y la prenda donde se prueba el logo.
 */
final class Portada
{
    /** @return array<string, mixed> */
    public static function datos(): array
    {
        $ahora = now();
        $familias = Escaparate::familias()->map(function (Familia $familia) {
            $prendas = Escaparate::prendas($familia);
            $desde = $prendas->map(fn (Prenda $p) => ($sinIva = Escaparate::desdeSinIva($p)) === null ? null : Iva::conIva($sinIva, Impuestos::tipoIva($p->clase_impuesto_id)))->filter()->min();

            return [
                'familia' => $familia,
                'foto' => $prendas->map(fn ($p) => Escaparate::fotoPrincipal($p))->filter()->first(),
                'desde' => $desde === null ? null : Formato::euros($desde),
            ];
        });

        $packs = self::packs();

        // La prenda donde se prueba el logo: la primera con maqueta y zonas.
        $prueba = Prenda::where('activa', true)->whereHas('variantes', fn ($q) => $q->aLaVenta())
            ->whereHas('fotos', fn ($q) => $q->where('tipo', TipoFoto::Maqueta->value)->whereHas('zonas'))
            ->with('familia')->orderBy('id')->first();

        return [
            'familias' => $familias,
            'packs' => $packs,
            'minimo' => ReglaMinimo::vigentes($ahora)->where('ambito', 'catalogo')->where('tipo', 'unidades_total')->max('valor'),
            'plazo' => Familia::where('activa', true)->min('plazo_base_dias'),
            'urlPrueba' => $prueba ? route('tienda.prenda', ['familia' => Escaparate::slug($prueba->familia, app()->getLocale()), 'prenda' => Escaparate::slug($prueba, app()->getLocale())]) : null,
        ];
    }

    /**
     * Los packs vigentes que se pueden comprar, listos para la tarjeta. Con modo, los de ese modo primero y
     * después los genéricos (D203).
     *
     * @return Collection<int, array{nombre: string, url: string, descripcion: ?string, unidades: int, precio: string, zonas: string, foto: ?Foto, imagen: ?string, modos: list<string>}>
     */
    public static function packs(?ModoCliente $modo = null): Collection
    {
        $packs = Pack::vigentes(now())->with(['lineas.prenda.fotos', 'zonas'])->orderBy('orden')->get()
            ->filter(fn (Pack $p) => $p->lineas->isNotEmpty() && $p->lineas->every(fn ($l) => $l->prenda?->activa));
        if ($modo !== null) {
            $packs = $packs->filter(fn (Pack $p) => $p->esPara($modo))->sortBy(fn (Pack $p) => $p->modos ? 0 : 1);
        }

        return $packs->map(fn (Pack $p) => [
            'nombre' => $p->nombre,
            'url' => route('packs.ver', ['pack' => $p->getTranslation('slug', app()->getLocale())]),
            'descripcion' => $p->descripcion,
            'unidades' => $p->unidades(),
            'precio' => Formato::euros(Iva::conIva($p->precio, Impuestos::tipoIva($p->clase_impuesto_id))),
            'zonas' => $p->zonas->map(fn (ZonaImpresion $z) => $z->nombre)->implode(', '),
            'foto' => Escaparate::fotoPrincipal($p->lineas->first()->prenda),
            'imagen' => $p->imagenUrl(), // D217
            'modos' => $p->modos ?: [], // D221: para filtrar en la portada; vacío = genérico, sale en todos
        ])->values();
    }

    /** @return array<string, mixed> */
    public static function vacia(): array
    {
        return ['familias' => collect(), 'packs' => collect(), 'minimo' => null, 'plazo' => null, 'urlPrueba' => null];
    }
}
