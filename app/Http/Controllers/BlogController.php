<?php

namespace App\Http\Controllers;

use App\Dominio\Catalogo\Escaparate;
use App\Models\EntradaBlog;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Blog (v2, D195): /es/blog y /es/blog/{slug} (y en inglés con su URL), solo lo publicado. */
class BlogController
{
    public function index(string $idioma): View
    {
        return view('tienda.blog.index', [
            'entradas' => EntradaBlog::publicadas()->orderByDesc('publicada_en')->paginate(12),
            'alternos' => collect(config('idiomas.disponibles'))->keys()->mapWithKeys(fn ($i) => [$i => route('blog', ['idioma' => $i])])->all(),
        ]);
    }

    public function ver(string $idioma, string $slug): View
    {
        $entrada = Escaparate::porSlug(EntradaBlog::publicadas(), $slug, $idioma)->firstOrFail();

        return view('tienda.blog.ver', [
            'entrada' => $entrada,
            'html' => Str::markdown((string) $entrada->contenido, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            'alternos' => collect(config('idiomas.disponibles'))->keys()->mapWithKeys(fn ($i) => [$i => route('blog.ver', ['idioma' => $i, 'slug' => Escaparate::slug($entrada, $i)])])->all(),
        ]);
    }
}
