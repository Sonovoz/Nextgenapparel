<?php

namespace App\Dominio\Carrito;

use App\Dominio\Disenos\Dueno;
use App\Models\Carrito;
use App\Models\Cupon;
use App\Models\Diseno;
use App\Models\LineaCarrito;
use Illuminate\Support\Facades\DB;

/**
 * Cambios en el carrito (paso 5.5). Cada línea se comprueba antes de guardarla
 * (ComprobarLinea) y los diseños tienen que ser de quien compra: nadie puede poner en
 * su carrito el diseño de otro conociendo su uuid. No se guarda ningún precio.
 */
final class EditarCarrito
{
    /** Freno al abuso: líneas por carrito (al sumar carritos al entrar no se pierde ninguna). */
    public const MAXIMO_LINEAS = 50;

    public function __construct(private readonly ComprobarLinea $comprobar) {}

    /**
     * @param  array<int|string, mixed>  $tallas  talla_id => unidades
     * @param  array<int, array{diseno: string, ancho_mm: int, alto_mm: int, desde_cuello_mm: int, desde_centro_mm: int}>  $impresiones  zona_id => impresión
     */
    public function anadirPrenda(Carrito $carrito, Dueno $dueno, int $prendaId, int $colorId, array $tallas, array $impresiones): LineaCarrito
    {
        $impresiones = $this->impresiones($dueno, $impresiones);
        $this->validar($this->comprobar->prenda($prendaId, $colorId, $tallas, $impresiones));

        return $this->guardar($carrito, [
            'tipo' => TipoLinea::Prenda, 'prenda_id' => $prendaId, 'color_id' => $colorId,
            'tallas' => ComprobarLinea::unidades($tallas),
        ], $impresiones);
    }

    /**
     * @param  array<int, array{color?: int, tallas?: array<int, int>}>  $elecciones  linea_pack_id => elección (reparto libre)
     * @param  array<int, array{diseno: string, ancho_mm: int, alto_mm: int, desde_cuello_mm: int, desde_centro_mm: int}>  $impresiones  zona_id => impresión
     */
    public function anadirPack(Carrito $carrito, Dueno $dueno, int $packId, array $elecciones, array $impresiones, ?string $coleccion = null): LineaCarrito
    {
        $impresiones = $this->impresiones($dueno, $impresiones);
        $elecciones = self::elecciones($elecciones);
        $this->validar($this->comprobar->pack($packId, $impresiones, now()));
        $this->validar($this->comprobar->reparto($packId, $elecciones, $impresiones, now()));

        return $this->guardar($carrito, ['tipo' => TipoLinea::Pack, 'pack_id' => $packId, 'elecciones' => $elecciones, 'coleccion' => filled($coleccion) ? trim($coleccion) : null], $impresiones);
    }

    /**
     * La línea se busca dentro del carrito de quien compra: nadie edita líneas ajenas
     * conociendo su uuid (revisión N3, B3).
     */
    public function linea(Carrito $carrito, string $uuid): LineaCarrito
    {
        return $carrito->lineas()->where('uuid', $uuid)->firstOrFail();
    }

    /** @param  array<int|string, mixed>  $tallas */
    public function cambiarTallas(Carrito $carrito, string $uuid, array $tallas): void
    {
        $linea = $this->linea($carrito, $uuid);
        if ($linea->tipo !== TipoLinea::Prenda) {
            throw new LineaNoValida(['cantidad_no_valida']);
        }
        $linea->loadMissing('impresiones');
        $this->validar($this->comprobar->prenda($linea->prenda_id, $linea->color_id, $tallas, ComprobarLinea::deLinea($linea)));

        $linea->update(['tallas' => ComprobarLinea::unidades($tallas)]);
        $carrito->touch();
    }

    public function quitar(Carrito $carrito, string $uuid): void
    {
        $this->linea($carrito, $uuid)->delete(); // impresiones en cascada
        $carrito->touch();
    }

    public function vaciar(Carrito $carrito): void
    {
        $carrito->lineas()->delete();
        $carrito->update(['cupon' => null]);
        $carrito->touch();
    }

    /** El cupón se guarda tal cual; si vale o no lo dice el motor cada vez que se valora. */
    public function ponerCupon(Carrito $carrito, ?string $codigo): void
    {
        $codigo = $codigo === null ? '' : Cupon::normalizar($codigo);
        $carrito->update(['cupon' => $codigo === '' ? null : mb_substr($codigo, 0, 50)]);
        $carrito->touch();
    }

    /**
     * Solo se guarda la forma que entiende el motor: linea_pack_id => {color, tallas}.
     *
     * @return array<int, array{color?: int, tallas?: array<int, int>}>
     */
    private static function elecciones(array $elecciones): array
    {
        $limpias = [];
        foreach ($elecciones as $lineaPack => $eleccion) {
            if (! ctype_digit((string) $lineaPack) || ! is_array($eleccion)) {
                throw new LineaNoValida(['reparto_pack_no_valido']);
            }
            $tallas = ComprobarLinea::unidades(is_array($eleccion['tallas'] ?? null) ? $eleccion['tallas'] : []);
            if ($tallas === null || (isset($eleccion['color']) && ! is_int($eleccion['color']))) {
                throw new LineaNoValida(['cantidad_no_valida']);
            }
            $limpias[(int) $lineaPack] = array_filter(['color' => $eleccion['color'] ?? null, 'tallas' => $tallas], fn ($v) => $v !== null && $v !== []);
        }

        return $limpias;
    }

    /** @param  list<string>  $problemas */
    private function validar(array $problemas): void
    {
        if ($problemas !== []) {
            throw new LineaNoValida($problemas);
        }
    }

    /**
     * Los diseños se buscan por su uuid y solo entre los de quien compra.
     *
     * @param  array<int|string, mixed>  $datos
     * @return list<Impresion>
     */
    private function impresiones(Dueno $dueno, array $datos): array
    {
        $uuids = array_values(array_filter(array_map(fn ($d) => is_array($d) && is_string($d['diseno'] ?? null) ? $d['diseno'] : null, $datos)));
        $suyos = $dueno->filtrar(Diseno::query())->whereIn('uuid', $uuids)->where('estado', '!=', 'rechazado')->pluck('id', 'uuid');

        $impresiones = [];
        foreach ($datos as $zona => $d) {
            if (! is_array($d) || ! ctype_digit((string) $zona)) {
                throw new LineaNoValida(['zona_no_disponible']);
            }
            foreach (['ancho_mm', 'alto_mm', 'desde_cuello_mm', 'desde_centro_mm'] as $campo) {
                if (! is_int($d[$campo] ?? null)) {
                    throw new LineaNoValida(['medida_no_valida']);
                }
            }
            $medidas = [$d['ancho_mm'], $d['alto_mm'], $d['desde_cuello_mm'], $d['desde_centro_mm']];
            if (array_key_exists('texto', $d)) {
                // «Escribir mi marca»: texto, fuente y tinta; la comprobación decide si valen.
                $impresiones[] = new Impresion((int) $zona, null, ...$medidas,
                    texto: is_string($d['texto']) ? trim($d['texto']) : '',
                    fuente: is_int($d['fuente'] ?? null) ? $d['fuente'] : -1,
                    tinta: is_string($d['tinta'] ?? null) ? $d['tinta'] : '');

                continue;
            }
            $diseno = is_string($d['diseno'] ?? null) ? $suyos->get($d['diseno']) : null; // B1: nada de arrays
            $impresiones[] = new Impresion((int) $zona, $diseno, ...$medidas);
        }

        return $impresiones;
    }

    /**
     * @param  array<string, mixed>  $columnas
     * @param  list<Impresion>  $impresiones
     */
    private function guardar(Carrito $carrito, array $columnas, array $impresiones): LineaCarrito
    {
        return DB::transaction(function () use ($carrito, $columnas, $impresiones): LineaCarrito {
            // Bloquea el carrito: dos peticiones a la vez no se saltan el máximo de líneas.
            $bloqueado = Carrito::whereKey($carrito->id)->lockForUpdate()->firstOrFail();
            if ($bloqueado->lineas()->count() >= self::MAXIMO_LINEAS) {
                throw new LineaNoValida(['demasiadas_lineas']);
            }

            $linea = $bloqueado->lineas()->create([...$columnas, 'orden' => (int) $bloqueado->lineas()->max('orden') + 1]);
            $linea->impresiones()->createMany(array_map(fn (Impresion $i) => $i->columnas(), $impresiones));
            $bloqueado->touch();

            return $linea->load('impresiones');
        });
    }
}
