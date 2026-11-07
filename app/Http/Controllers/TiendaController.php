<?php

namespace App\Http\Controllers;

use App\Dominio\Catalogo\Escaparate;
use App\Dominio\Catalogo\Portada;
use App\Dominio\Seo\DatosEstructurados;
use App\Models\Talla;
use Illuminate\View\View;

class TiendaController
{
    public function portada(string $idioma): View
    {
        // Si la base de datos no responde, la portada se muestra sin catálogo (autosuficiencia).
        $datos = rescue(fn () => Portada::datos(), Portada::vacia(), report: false);

        return view('portada', [
            ...$datos,
            'alternos' => $this->alternos(fn ($i) => route('portada', ['idioma' => $i])),
            'seo' => [
                'imagen' => asset('img/portada/pareja-1600.webp'),
                'esquemas' => rescue(fn () => [DatosEstructurados::organizacion(), DatosEstructurados::sitioWeb($idioma)], [], report: false),
            ],
        ]);
    }

    public function familia(string $idioma, string $familia): View
    {
        $familia = Escaparate::familia($familia, $idioma);

        $prendas = Escaparate::prendas($familia);
        $alternos = $this->alternos(fn ($i) => route('tienda.familia', ['idioma' => $i, 'familia' => Escaparate::slug($familia, $i)]));

        return view('tienda.familia', [
            'familia' => $familia,
            'prendas' => $prendas,
            'alternos' => $alternos,
            'seo' => [
                'imagen' => $prendas->map(fn ($p) => DatosEstructurados::imagenPrincipal($p))->filter()->first(),
                'esquemas' => [DatosEstructurados::migas([
                    __('tienda.inicio') => route('portada'),
                    $familia->nombre => $alternos[$idioma],
                ])],
            ],
        ]);
    }

    public function prenda(string $idioma, string $familia, string $prenda): View
    {
        $familia = Escaparate::familia($familia, $idioma);
        $prenda = Escaparate::prenda($familia, $prenda, $idioma);
        $prenda->load(['zonas' => fn ($q) => $q->where('activa', true)->orderBy('orden'), 'medidas.talla', 'fotos']);
        $alternos = $this->alternos(fn ($i) => route('tienda.prenda', ['idioma' => $i, 'familia' => Escaparate::slug($familia, $i), 'prenda' => Escaparate::slug($prenda, $i)]));

        return view('tienda.prenda', [
            'familia' => $familia,
            'prenda' => $prenda,
            'colores' => Escaparate::colores($prenda),
            'tallas' => Talla::whereIn('id', $prenda->variantes()->aLaVenta()->distinct()->pluck('talla_id'))->orderBy('orden')->pluck('nombre'),
            'medidas' => $prenda->medidas->sortBy(fn ($m) => $m->talla->orden)->values(),
            'precio' => Escaparate::precioDesde($prenda),
            'alternos' => $alternos,
            'seo' => [
                'tipo' => 'product',
                'imagen' => DatosEstructurados::imagenPrincipal($prenda),
                'esquemas' => [
                    DatosEstructurados::producto($prenda, $familia, $alternos[$idioma]),
                    DatosEstructurados::migas([
                        __('tienda.inicio') => route('portada'),
                        $familia->nombre => route('tienda.familia', ['familia' => Escaparate::slug($familia, $idioma)]),
                        $prenda->nombre => $alternos[$idioma],
                    ]),
                ],
            ],
        ]);
    }

    /** URL de la misma página en cada idioma (selector de idioma y hreflang). */
    private function alternos(callable $url): array
    {
        return collect(config('idiomas.disponibles'))->keys()->mapWithKeys(fn ($i) => [$i => $url($i)])->all();
    }
}
