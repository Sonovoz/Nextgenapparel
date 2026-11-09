<?php

namespace App\Http\Controllers;

use App\Dominio\Catalogo\Escaparate;
use App\Dominio\Seo\Indexacion;
use App\Models\EntradaBlog;
use Illuminate\Http\Response;

/** robots.txt y sitemaps por idioma (ficha § 12). */
class SeoController
{
    public function robots(): Response
    {
        $lineas = Indexacion::permitida()
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /livewire', '', 'Sitemap: '.url('/sitemap.xml')]
            : ['# Tienda en construcción o entorno de pruebas: fuera de los buscadores.', 'User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lineas)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function indice(): Response
    {
        return $this->xml(view('seo.sitemap-indice', ['idiomas' => array_keys(config('idiomas.disponibles'))]));
    }

    public function sitemap(string $idioma): Response
    {
        $idiomas = array_keys(config('idiomas.disponibles'));
        $urls = [];

        $urls[] = $this->entrada(fn ($i) => route('portada', ['idioma' => $i]), $idioma, $idiomas);
        $urls[] = $this->entrada(fn ($i) => route('nosotros', ['idioma' => $i, 'pagina' => trans('portada.ruta_nosotros', [], $i)]), $idioma, $idiomas);

        foreach (Escaparate::familias() as $familia) {
            $urls[] = $this->entrada(fn ($i) => route('tienda.familia', ['idioma' => $i, 'familia' => Escaparate::slug($familia, $i)]), $idioma, $idiomas, $familia->updated_at);

            foreach (Escaparate::prendas($familia) as $prenda) {
                $entrada = $this->entrada(
                    fn ($i) => route('tienda.prenda', ['idioma' => $i, 'familia' => Escaparate::slug($familia, $i), 'prenda' => Escaparate::slug($prenda, $i)]),
                    $idioma, $idiomas, $prenda->updated_at,
                );
                $entrada['imagen'] = Escaparate::fotoPrincipal($prenda)?->url();
                $urls[] = $entrada;
            }
        }

        // Blog (D195): el índice y cada entrada publicada.
        $urls[] = $this->entrada(fn ($i) => route('blog', ['idioma' => $i]), $idioma, $idiomas);
        foreach (EntradaBlog::publicadas()->get() as $articulo) {
            $urls[] = $this->entrada(fn ($i) => route('blog.ver', ['idioma' => $i, 'slug' => Escaparate::slug($articulo, $i)]), $idioma, $idiomas, $articulo->updated_at);
        }

        return $this->xml(view('seo.sitemap', ['urls' => $urls]));
    }

    /** @return array{loc: string, alternos: array<string, string>, lastmod: ?string, imagen: ?string} */
    private function entrada(callable $url, string $idioma, array $idiomas, $fecha = null): array
    {
        $alternos = [];
        foreach ($idiomas as $i) {
            $alternos[$i] = $url($i);
        }
        $alternos['x-default'] = $alternos[config('idiomas.predeterminado')];

        return ['loc' => $alternos[$idioma], 'alternos' => $alternos, 'lastmod' => $fecha?->toAtomString(), 'imagen' => null];
    }

    private function xml($vista): Response
    {
        return response($vista->render(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
