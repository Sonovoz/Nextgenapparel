<?php

namespace App\Http\Controllers;

use App\Dominio\Carrito\CarritoActual;
use App\Dominio\Carrito\DisenosDeLinea;
use App\Dominio\Carrito\EditarCarrito;
use App\Dominio\Carrito\LineaNoValida;
use App\Dominio\Carrito\Maqueta;
use App\Dominio\Carrito\TipoLinea;
use App\Dominio\Carrito\ValorarCarrito;
use App\Dominio\Catalogo\OtrasPrendas;
use App\Dominio\Disenos\Dueno;
use App\Dominio\Disenos\GuardarDiseno;
use App\Models\Carrito;
use App\Models\Diseno;
use App\Models\ImpresionCarrito;
use App\Models\LineaCarrito;
use App\Models\Prenda;
use App\Models\Talla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * El carrito en la tienda (paso 5.6): «Añadir a mi pedido» desde el configurador y la
 * página del carrito con sus maquetas, cantidades, cupón y el total del motor. Toda la
 * lógica está en App\Dominio\Carrito; aquí solo se traduce la petición.
 */
class CarritoController
{
    public function ver(Request $request, ValorarCarrito $valorar): View
    {
        $carrito = CarritoActual::de($request);
        $valoracion = $carrito === null ? null : $valorar($carrito);
        $nombresTalla = Talla::orderBy('orden')->pluck('nombre', 'id');
        $lineas = collect($valoracion?->lineas ?? [])->map(fn (array $v) => [
            ...$v,
            'maqueta' => Maqueta::de($v['linea']),
            // Tallas que se pueden pedir ahora en ese color (y las que ya lleva, aunque se hayan retirado).
            'tallas' => self::tallasEditables($v['linea'], $nombresTalla->all()),
        ]);

        return view('carrito.ver', [
            'carrito' => $carrito,
            'valoracion' => $valoracion,
            'lineas' => $lineas,
            'completa' => self::completa($carrito),
            'alternos' => collect(config('idiomas.disponibles'))->keys()->mapWithKeys(fn ($i) => [$i => self::url('ver', idioma: $i)])->all(),
        ]);
    }

    /**
     * «Completa tu colección» (D213): otras prendas con el diseño de la última línea ya puesto, para
     * seguir el pedido sin volver a empezar. Las que ya están en el carrito no se ofrecen.
     *
     * @return list<array{nombre: string, url: string, desde: ?string, previa: array{foto: ?string, capas: list<array{url: string, x: float, y: float, ancho: float, alto: float}>}}>
     */
    private static function completa(?Carrito $carrito): array
    {
        $ultima = DisenosDeLinea::ultimaConDisenos($carrito);
        if ($carrito === null || $ultima === null) {
            return [];
        }
        $disenos = DisenosDeLinea::deLinea($ultima);
        $enCarrito = $carrito->lineas()->whereNotNull('prenda_id')->pluck('prenda_id')->map(fn ($id) => (int) $id)->all();

        return array_map(fn (array $p) => [
            'nombre' => $p['nombre'],
            'url' => $p['url'].'?con='.$ultima->uuid,
            'desde' => $p['desde'],
            'previa' => OtrasPrendas::previa($p, $disenos),
        ], OtrasPrendas::para($enCarrito));
    }

    /** Desde el configurador: una línea por color, todas con los mismos diseños. Todo o nada. */
    public function anadir(Request $request, EditarCarrito $editar): JsonResponse
    {
        $datos = $request->validate([
            'prenda' => ['required', 'integer'],
            'lineas' => ['required', 'array', 'min:1', 'max:10'],
            'lineas.*.color' => ['required', 'integer'],
            'lineas.*.tallas' => ['required', 'array', 'max:20'],
            'impresiones' => ['required', 'array', 'min:1', 'max:10'],
        ]);
        $prenda = Prenda::where('activa', true)->findOrFail($datos['prenda']);
        $dueno = Dueno::de($request);
        $carrito = CarritoActual::de($request, crear: true);

        try {
            $ultima = DB::transaction(function () use ($datos, $prenda, $dueno, $carrito, $editar): ?LineaCarrito {
                $ultima = null;
                foreach ($datos['lineas'] as $linea) {
                    $ultima = $editar->anadirPrenda($carrito, $dueno, $prenda->id, $linea['color'], $linea['tallas'], $datos['impresiones']);
                }

                return $ultima;
            });
        } catch (LineaNoValida $e) {
            return response()->json(['message' => implode(' ', $e->textos()), 'problemas' => $e->textos()], 422);
        }

        // La línea añadida: «Añade otra prenda con este diseño» la lleva a la siguiente prenda (D213).
        return response()->json(['url' => self::url('ver'), 'lineas' => $carrito->lineas()->count(), 'linea' => $ultima?->uuid], 201);
    }

    public function tallas(Request $request, EditarCarrito $editar, string $uuid): RedirectResponse
    {
        $datos = $request->validate(['tallas' => ['required', 'array', 'max:20'], 'tallas.*' => ['nullable', 'integer', 'min:0']]);
        $carrito = $this->carrito($request);
        $tallas = array_map(fn ($u) => (int) $u, array_filter($datos['tallas'], fn ($u) => $u !== null));

        try {
            $editar->cambiarTallas($carrito, $uuid, $tallas);
        } catch (LineaNoValida $e) {
            throw ValidationException::withMessages(['linea_'.$uuid => $e->textos()]);
        }

        // Si se guardaron al pulsar «Cerrar el pedido», se sigue al cierre con las cantidades nuevas.
        if ($request->input('seguir') === 'cerrar') {
            return redirect(self::url('cerrar'));
        }

        return redirect(self::url('ver').'#linea-'.$uuid)->with('estado', __('carrito.cantidades_guardadas'));
    }

    public function quitar(Request $request, EditarCarrito $editar, string $uuid): RedirectResponse
    {
        $editar->quitar($this->carrito($request), $uuid);

        return redirect(self::url('ver'))->with('estado', __('carrito.linea_quitada'));
    }

    public function cupon(Request $request, EditarCarrito $editar): RedirectResponse
    {
        $datos = $request->validate(['cupon' => ['nullable', 'string', 'max:50']]);
        $editar->ponerCupon($this->carrito($request), $datos['cupon'] ?? null);

        return redirect(self::url('ver').'#cupon')->with('estado', __($datos['cupon'] ?? null ? 'carrito.cupon_guardado' : 'carrito.cupon_quitado'));
    }

    /**
     * Miniatura de un diseño del carrito. Va aparte de la de la biblioteca porque un
     * invitado puede llevar en su carrito diseños de otra visita (otra sesión).
     */
    public function miniatura(Request $request, string $diseno): Response
    {
        $carrito = CarritoActual::de($request);
        $diseno = Diseno::where('uuid', $diseno)->firstOrFail();
        abort_unless($carrito !== null && ImpresionCarrito::where('diseno_id', $diseno->id)
            ->whereIn('linea_carrito_id', $carrito->lineas()->select('id'))->exists(), 404);

        return Storage::disk(GuardarDiseno::DISCO)->response($diseno->miniatura, null, [
            'Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * @param  array<int, string>  $nombres
     * @return list<array{id: int, nombre: string, unidades: int}>
     */
    private static function tallasEditables(LineaCarrito $linea, array $nombres): array
    {
        $llevas = $linea->unidadesPorTalla();
        $aLaVenta = $linea->tipo === TipoLinea::Prenda && $linea->prenda !== null
            ? $linea->prenda->variantes()->aLaVenta()->where('color_id', $linea->color_id)->pluck('talla_id')->all()
            : [];
        $ids = array_values(array_unique([...$aLaVenta, ...array_keys($llevas)]));
        usort($ids, fn ($a, $b) => array_search($a, array_keys($nombres)) <=> array_search($b, array_keys($nombres)));

        return array_map(fn (int $id) => ['id' => $id, 'nombre' => $nombres[$id] ?? '?', 'unidades' => $llevas[$id] ?? 0], $ids);
    }

    public static function url(string $accion, array $parametros = [], ?string $idioma = null): string
    {
        $idioma ??= app()->getLocale();

        return route("carrito.{$idioma}.{$accion}", $parametros);
    }

    private function carrito(Request $request): Carrito
    {
        return CarritoActual::de($request) ?? abort(404);
    }
}
