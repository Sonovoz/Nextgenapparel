<?php

namespace App\Dominio\Seo;

use App\Dominio\Catalogo\Escaparate;
use App\Dominio\Catalogo\TipoFoto;
use App\Dominio\Precios\Iva;
use App\Models\Ajuste;
use App\Models\Familia;
use App\Models\Prenda;

/**
 * Datos estructurados schema.org (JSON-LD) de la tienda: Organization,
 * WebSite, BreadcrumbList y Product con su oferta. Los precios salen del
 * catálogo con IVA, igual que en la página (nunca de la IA).
 */
final class DatosEstructurados
{
    /** @return array<string, mixed> */
    public static function organizacion(): array
    {
        $nombre = (string) Ajuste::obtener('tienda.nombre');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => url('/').'#organizacion',
            'name' => $nombre,
            'url' => url('/'),
            'email' => Ajuste::obtener('tienda.correo_contacto') ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public static function sitioWeb(string $idioma): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => (string) Ajuste::obtener('tienda.nombre'),
            'url' => route('portada', ['idioma' => $idioma]),
            'inLanguage' => $idioma,
            'publisher' => ['@id' => url('/').'#organizacion'],
        ];
    }

    /**
     * @param  array<string, string>  $pasos  nombre => URL, en orden
     * @return array<string, mixed>
     */
    public static function migas(array $pasos): array
    {
        $elementos = [];
        $posicion = 1;
        foreach ($pasos as $nombre => $url) {
            $elementos[] = ['@type' => 'ListItem', 'position' => $posicion++, 'name' => $nombre, 'item' => $url];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $elementos];
    }

    /** @return array<string, mixed> */
    public static function producto(Prenda $prenda, Familia $familia, string $url): array
    {
        $precios = $prenda->variantes()->where('activa', true)->pluck('precio_base');
        $imagenes = $prenda->fotos->sortBy(fn ($f) => $f->tipo === TipoFoto::Maqueta ? 0 : 1)->map->url()->values()->all();

        $producto = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $prenda->nombre,
            'description' => (string) $prenda->descripcion,
            'category' => $familia->nombre,
            'url' => $url,
            'brand' => ['@type' => 'Brand', 'name' => (string) Ajuste::obtener('tienda.nombre')],
        ];

        if ($imagenes !== []) {
            $producto['image'] = $imagenes;
        }

        if ($precios->isNotEmpty()) {
            $producto['offers'] = [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'EUR',
                'lowPrice' => self::importe(Iva::conIva((int) $precios->min())),
                'highPrice' => self::importe(Iva::conIva((int) $precios->max())),
                'offerCount' => $precios->count(),
                'availability' => 'https://schema.org/InStock',
                'url' => $url,
                'seller' => ['@id' => url('/').'#organizacion'],
            ];
        }

        return $producto;
    }

    /** Céntimos → «5.08» (formato que pide schema.org). */
    private static function importe(int $centimos): string
    {
        return intdiv($centimos, 100).'.'.str_pad((string) ($centimos % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Imagen para compartir (Open Graph): la foto principal de la prenda. */
    public static function imagenPrincipal(Prenda $prenda): ?string
    {
        return Escaparate::fotoPrincipal($prenda)?->url();
    }
}
