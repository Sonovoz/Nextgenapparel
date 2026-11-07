<?php

namespace App\Http\Controllers;

use App\Dominio\Catalogo\Escaparate;
use App\Models\Talla;
use Illuminate\View\View;

class TiendaController
{
    public function portada(string $idioma): View
    {
        return view('portada', [
            // Si la base de datos no responde, la portada se muestra sin el catálogo.
            'familias' => rescue(fn () => Escaparate::familias()->map(fn ($f) => ['familia' => $f, 'foto' => Escaparate::prendas($f)->map(fn ($p) => Escaparate::fotoPrincipal($p))->filter()->first()]), collect(), report: false),
            'alternos' => $this->alternos(fn ($i) => route('portada', ['idioma' => $i])),
        ]);
    }

    public function familia(string $idioma, string $familia): View
    {
        $familia = Escaparate::familia($familia, $idioma);

        return view('tienda.familia', [
            'familia' => $familia,
            'prendas' => Escaparate::prendas($familia),
            'alternos' => $this->alternos(fn ($i) => route('tienda.familia', ['idioma' => $i, 'familia' => Escaparate::slug($familia, $i)])),
        ]);
    }

    public function prenda(string $idioma, string $familia, string $prenda): View
    {
        $familia = Escaparate::familia($familia, $idioma);
        $prenda = Escaparate::prenda($familia, $prenda, $idioma);
        $prenda->load(['zonas' => fn ($q) => $q->where('activa', true)->orderBy('orden'), 'medidas.talla']);

        return view('tienda.prenda', [
            'familia' => $familia,
            'prenda' => $prenda,
            'colores' => Escaparate::colores($prenda),
            'tallas' => Talla::whereIn('id', $prenda->variantes()->where('activa', true)->distinct()->pluck('talla_id'))->orderBy('orden')->pluck('nombre'),
            'medidas' => $prenda->medidas->sortBy(fn ($m) => $m->talla->orden)->values(),
            'precio' => Escaparate::precioDesde($prenda),
            'alternos' => $this->alternos(fn ($i) => route('tienda.prenda', ['idioma' => $i, 'familia' => Escaparate::slug($familia, $i), 'prenda' => Escaparate::slug($prenda, $i)])),
        ]);
    }

    /** URL de la misma página en cada idioma (selector de idioma y hreflang). */
    private function alternos(callable $url): array
    {
        return collect(config('idiomas.disponibles'))->keys()->mapWithKeys(fn ($i) => [$i => $url($i)])->all();
    }
}
