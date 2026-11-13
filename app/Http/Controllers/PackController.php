<?php

namespace App\Http\Controllers;

use App\Dominio\Carrito\CarritoActual;
use App\Dominio\Carrito\ColocacionInicial;
use App\Dominio\Carrito\EditarCarrito;
use App\Dominio\Carrito\Impresion;
use App\Dominio\Carrito\LineaNoValida;
use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\CopiarZonas;
use App\Dominio\Catalogo\Escaparate;
use App\Dominio\Disenos\AnalizadorPng;
use App\Dominio\Disenos\DisenoNoValido;
use App\Dominio\Disenos\Dueno;
use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Precios\Formato;
use App\Dominio\Precios\RepartoPack;
use App\Models\Color;
use App\Models\Diseno;
use App\Models\Foto;
use App\Models\LineaPack;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\Talla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Página de un pack (paso 5.7), sencilla a propósito: el cliente elige uno de sus
 * diseños (o sube uno, o escribe un texto), reparte las tallas si el pack lo permite y
 * lo añade al carrito. El diseño se coloca solo en cada zona del pack (ColocacionInicial);
 * no hay ajustador ni zonas extra.
 */
class PackController
{
    public function ver(Request $request, string $idioma, string $pack): View
    {
        $pack = $this->pack($pack, $idioma);
        $dueno = Dueno::de($request, crear: false);
        $tallas = Talla::orderBy('orden')->get(['id', 'nombre']);
        $libre = $pack->reparto === RepartoPack::Libre;
        $zonasPack = $pack->zonas->pluck('id')->all();

        return view('packs.ver', [
            'pack' => $pack,
            'precio' => Formato::euros($pack->precioParaMostrar()['con_iva']),
            'neto' => ($n = $pack->precioParaMostrar()['neto']) === null ? null : Formato::euros($n), // D219
            'libre' => $libre,
            // D220: cada prenda del pack con sus colores (muestra y foto de ese color) y sus tallas en orden de talla.
            'lineas' => $pack->lineas->map(function (LineaPack $l) use ($libre, $tallas, $zonasPack) {
                $ids = array_map('intval', $libre ? ($l->tallas_permitidas ?? []) : array_keys($l->reparto_tallas ?? []));
                $general = Escaparate::fotoPrincipal($l->prenda)?->url();

                return [
                    'linea' => $l,
                    'colores' => Color::whereIn('id', $l->colores ?? [])->orderBy('orden')->get()->map(fn (Color $c) => [
                        'id' => $c->id, 'nombre' => $c->nombre, 'hex' => $c->codigo_hex, 'foto' => self::fotoDeColor($l->prenda, $c->id) ?? $general,
                    ])->all(),
                    'tallas' => $tallas->whereIn('id', $ids)->map(fn (Talla $t) => ['id' => $t->id, 'nombre' => $t->nombre, 'fijas' => (int) ($l->reparto_tallas[$t->id] ?? 0)])->values()->all(),
                    'foto' => $general,
                    'maqueta' => self::maqueta($l, $zonasPack),
                ];
            }),
            'disenos' => $dueno === null ? collect() : $dueno->filtrar(Diseno::query())->where('estado', '!=', 'rechazado')->latest('id')->limit(24)->get(),
            'maxMb' => AnalizadorPng::maxMbEfectivo(),
            'imagen' => $pack->imagenUrl() ?? Escaparate::fotoPrincipal($pack->lineas->first()->prenda)?->url(), // D217
            'alternos' => collect(config('idiomas.disponibles'))->keys()
                ->mapWithKeys(fn ($i) => [$i => route('packs.ver', ['idioma' => $i, 'pack' => $pack->getTranslation('slug', $i)])])->all(),
        ]);
    }

    public function anadir(Request $request, EditarCarrito $editar, string $idioma, string $pack): RedirectResponse
    {
        $pack = $this->pack($pack, $idioma);
        $datos = $request->validate([
            'origen' => ['required', Rule::in(['diseno', 'texto'])],
            'diseno' => ['required_if:origen,diseno', 'nullable', 'uuid'],
            'texto' => ['required_if:origen,texto', 'nullable', 'string', 'max:'.Impresion::MAXIMO_TEXTO],
            'fuente' => ['nullable', 'integer', 'min:0', 'max:'.(Impresion::FUENTES - 1)],
            'tinta' => ['nullable', Rule::in(Impresion::TINTAS)],
            'coleccion' => ['nullable', 'string', 'max:60'],
            'elecciones' => ['nullable', 'array'],
            'elecciones.*.color' => ['nullable', 'integer'],
            'elecciones.*.tallas' => ['nullable', 'array'],
            'elecciones.*.tallas.*' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ], [], ['diseno' => __('packs.campo_diseno'), 'texto' => __('packs.campo_texto')]);

        $esTexto = $datos['origen'] === 'texto';
        $diseno = $esTexto ? null : Dueno::de($request)->filtrar(Diseno::query())->where('uuid', $datos['diseno'])->first();
        $proporcion = $esTexto ? ColocacionInicial::deTexto($datos['texto']) : ($diseno ? $diseno->util_ancho_px / max(1, $diseno->util_alto_px) : 1.0);
        $impresiones = [];
        foreach ($pack->zonas as $zona) {
            $impresiones[$zona->id] = [...ColocacionInicial::en($zona, $proporcion),
                ...($esTexto ? ['texto' => $datos['texto'], 'fuente' => (int) ($datos['fuente'] ?? 0), 'tinta' => $datos['tinta'] ?? 'auto'] : ['diseno' => (string) $datos['diseno']])];
        }
        // Del formulario llegan textos: el carrito quiere enteros.
        $elecciones = collect($datos['elecciones'] ?? [])->map(fn ($e) => array_filter([
            'color' => isset($e['color']) ? (int) $e['color'] : null,
            'tallas' => array_map('intval', array_filter($e['tallas'] ?? [], fn ($u) => $u !== null)),
        ], fn ($v) => $v !== null))->all();

        try {
            $editar->anadirPack(CarritoActual::de($request, crear: true), Dueno::de($request), $pack->id, $elecciones, $impresiones, $datos['coleccion'] ?? null);
        } catch (LineaNoValida $e) {
            throw ValidationException::withMessages(['pack' => $e->textos()]);
        }

        return redirect(CarritoController::url('ver'))->with('estado', __('packs.anadido', ['pack' => $pack->nombre]));
    }

    /** Subir un PNG desde la página del pack (sin JavaScript): vuelve con él elegido. */
    public function subir(Request $request, GuardarDiseno $guardar, string $idioma, string $pack): RedirectResponse
    {
        $this->pack($pack, $idioma);
        $maxMb = AnalizadorPng::maxMbEfectivo();
        $request->validate([
            'archivo' => ['required', 'file', 'max:'.($maxMb * 1024)],
        ], [
            'archivo.required' => __('disenos.error_sin_archivo'),
            'archivo.max' => __('disenos.error_pesado', ['mb' => $maxMb]),
        ]);

        try {
            $diseno = $guardar($request->file('archivo'), Dueno::de($request));
        } catch (DisenoNoValido $error) {
            throw ValidationException::withMessages(['archivo' => $error->mensaje()]);
        }

        return back()->with('diseno_elegido', $diseno->uuid)->with('estado', __('packs.diseno_subido'));
    }

    /**
     * D222: maqueta viva del pack. Por color de la línea, las caras de la prenda con alguna zona del pack
     * y dónde está cada zona en la foto (en %). El diseño se dibuja encajado, centrado y arriba en la zona,
     * igual que lo coloca ColocacionInicial al añadir.
     *
     * @param  list<int>  $zonasPack
     * @return array<int, list<array{url: string, zonas: list<array{x: float, y: float, ancho: float, alto: float}>}>>
     */
    private static function maqueta(LineaPack $linea, array $zonasPack): array
    {
        $colores = array_map('intval', $linea->colores ?? []);
        $maqueta = [];
        foreach (Escaparate::colores($linea->prenda) as $color) {
            if ($colores !== [] && ! in_array($color['id'], $colores, true)) {
                continue;
            }
            $caras = collect($color['caras'])->filter()->map(fn (array $cara) => [
                'url' => (string) $cara['url'],
                'zonas' => collect($cara['zonas'])->filter(fn (array $z) => in_array($z['id'], $zonasPack, true))
                    ->map(fn (array $z) => ['x' => (float) $z['x'], 'y' => (float) $z['y'], 'ancho' => (float) $z['ancho'], 'alto' => (float) $z['alto']])->values()->all(),
            ]);
            $conZonas = $caras->filter(fn (array $c) => $c['zonas'] !== [])->values()->all();
            $maqueta[(int) $color['id']] = $conZonas !== [] ? $conZonas : $caras->take(1)->values()->all();
        }

        return $maqueta;
    }

    /** Foto de la prenda en ese color: su maqueta delantera o, si no hay, cualquiera de ese color. */
    private static function fotoDeColor(Prenda $prenda, int $colorId): ?string
    {
        $fotos = Foto::where('prenda_id', $prenda->id)->where('color_id', $colorId)->orderBy('orden')->get();
        $delantera = $fotos->first(function (Foto $f): bool {
            $cara = $f->getAttribute('cara');

            return CopiarZonas::esMaqueta($f) && ($cara instanceof Cara ? $cara : Cara::tryFrom((string) $cara)) === Cara::Delantera;
        });

        return ($delantera ?? $fotos->first())?->url();
    }

    private function pack(string $slug, string $idioma): Pack
    {
        $pack = Pack::vigentes(now())->where("slug->{$idioma}", $slug)->with(['lineas.prenda', 'zonas'])->firstOrFail();
        abort_if($pack->lineas->isEmpty() || $pack->lineas->contains(fn ($l) => ! $l->prenda?->activa), 404);

        return $pack;
    }
}
