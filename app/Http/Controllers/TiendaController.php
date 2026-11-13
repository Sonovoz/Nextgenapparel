<?php

namespace App\Http\Controllers;

use App\Dominio\Carrito\CarritoActual;
use App\Dominio\Carrito\DisenosDeLinea;
use App\Dominio\Catalogo\Escaparate;
use App\Dominio\Catalogo\OtrasPrendas;
use App\Dominio\Catalogo\Portada;
use App\Dominio\Precios\Configurador;
use App\Dominio\Precios\Formato;
use App\Dominio\Precios\Motor\MotorPrecios;
use App\Dominio\Seo\DatosEstructurados;
use App\Models\Carrito;
use App\Models\LineaCarrito;
use App\Models\Talla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    /** Sobre nosotros (D145): página fija, sin base de datos, con la URL traducida en cada idioma. */
    public function nosotros(string $idioma, string $pagina): View|RedirectResponse
    {
        $alternos = $this->alternos(fn ($i) => route('nosotros', ['idioma' => $i, 'pagina' => trans('portada.ruta_nosotros', [], $i)]));

        // /es/about-us lleva a /es/sobre-nosotros: una sola URL por idioma.
        if ($pagina !== trans('portada.ruta_nosotros', [], $idioma)) {
            return redirect($alternos[$idioma], 301);
        }

        return view('nosotros', [
            'alternos' => $alternos,
            'seo' => [
                'imagen' => asset('img/portada/camiseta-dragon-1120.webp'),
                'esquemas' => [DatosEstructurados::migas([
                    __('tienda.inicio') => route('portada'),
                    __('nosotros.titulo_corto') => $alternos[$idioma],
                ])],
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
            // D213: diseños que vienen de otra prenda del pedido («Añade otra prenda con este diseño»),
            // otras prendas para seguir y lo que ya lleva el pedido.
            'llevar' => DisenosDeLinea::de($carrito = CarritoActual::de(request()), request()->query('con')),
            'otras' => OtrasPrendas::para([$prenda->id]),
            'pedidoActual' => self::resumenPedido($carrito),
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

    /**
     * Precio en directo del configurador (D142): una prenda con sus diseños o,
     * con cantidades, el pedido de esa prenda calculado por el motor.
     */
    public function precio(Request $request, Configurador $configurador, string $idioma, string $familia, string $prenda): JsonResponse
    {
        $prenda = Escaparate::prenda(Escaparate::familia($familia, $idioma), $prenda, $idioma);
        $datos = $request->validate([
            'color' => ['required', 'integer'],
            'zonas' => ['array', 'max:10'],
            'zonas.*' => ['integer'],
            'cantidades' => ['array', 'max:30'],
            'cantidades.*' => ['array', 'max:20'],
            'cantidades.*.*' => ['integer', 'min:0', 'max:'.MotorPrecios::MAXIMO_UNIDADES],
        ]);

        $cantidades = [];
        foreach ($datos['cantidades'] ?? [] as $color => $tallas) {
            $claves = [$color, ...array_keys($tallas)];
            abort_unless(collect($claves)->every(fn ($c) => ctype_digit((string) $c)), 422);
            $cantidades[(int) $color] = array_map('intval', $tallas);
        }

        $r = $configurador->calcular($prenda, (int) $datos['color'], array_map('intval', $datos['zonas'] ?? []), $cantidades);
        $euros = fn (?int $c) => $c === null ? null : Formato::euros($c);

        return response()->json([...$r, 'por_unidad' => $euros($r['por_unidad']), 'total' => $euros($r['total'])]);
    }

    /** URL de la misma página en cada idioma (selector de idioma y hreflang). */
    private function alternos(callable $url): array
    {
        return collect(config('idiomas.disponibles'))->keys()->mapWithKeys(fn ($i) => [$i => $url($i)])->all();
    }

    /** «Tu pedido: 2 prendas · 40 uds» para el configurador, o null con el carrito vacío. @return array{lineas: int, unidades: int}|null */
    private static function resumenPedido(?Carrito $carrito): ?array
    {
        $lineas = $carrito?->lineas()->get() ?? collect();
        if ($lineas->isEmpty()) {
            return null;
        }

        return ['lineas' => $lineas->count(), 'unidades' => (int) $lineas->sum(fn (LineaCarrito $l) => array_sum($l->unidadesPorTalla()))];
    }
}
