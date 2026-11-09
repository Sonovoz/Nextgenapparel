<?php

namespace App\Dominio\Precios\Motor;

use App\Dominio\Precios\Formato;
use App\Models\Familia;
use App\Models\GrupoCliente;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\ReglaPrecio;

/**
 * Mensaje para el cliente: clave de lang/{idioma}/precios.php y sus datos.
 *
 * Los datos que dependen del idioma se guardan como referencias y se
 * resuelven al mostrarlo, así el mismo mensaje (guardado en el pedido) sale
 * bien en español y en inglés:
 *   ['t' => 'precios.el_pedido']              palabra traducible
 *   ['euros' => 20000]                        importe con el formato del idioma
 *   ['modelo' => 'prenda', 'id' => 3]         nombre traducido (familia, prenda, pack, regla)
 *   ['familias' => [1, 2]] / ['prendas' => …] «polos o sudaderas»
 *   ['n' => 5, 'de' => …]                     «5 camisetas»
 *   ['lista' => […], 'union' => 'y']          «5 camisetas y 10 sudaderas»
 */
final class Mensaje
{
    private const MODELOS = ['familia' => Familia::class, 'prenda' => Prenda::class, 'pack' => Pack::class, 'regla' => ReglaPrecio::class, 'grupo' => GrupoCliente::class];

    /** @param  array<string, mixed>  $datos */
    public function __construct(
        public readonly string $clave,
        public readonly array $datos = [],
        public readonly ?int $cantidad = null,
    ) {}

    public function texto(?string $idioma = null): string
    {
        $idioma ??= app()->getLocale();
        $datos = array_map(fn ($valor) => $this->resolver($valor, $idioma), $this->datos);
        $linea = 'precios.'.$this->clave;

        return $this->cantidad === null
            ? __($linea, $datos, $idioma)
            : trans_choice($linea, $this->cantidad, $datos, $idioma);
    }

    private function resolver(mixed $valor, string $idioma): string
    {
        if (! is_array($valor)) {
            return (string) $valor;
        }

        return match (true) {
            isset($valor['t']) => __($valor['t'], [], $idioma),
            isset($valor['euros']) => Formato::euros((int) $valor['euros'], $idioma),
            isset($valor['modelo']) => $this->nombre($valor['modelo'], (int) $valor['id'], $idioma),
            isset($valor['familias']) => $this->unir(Familia::whereIn('id', $valor['familias'])->orderBy('orden')->get()
                ->map(fn ($f) => mb_strtolower($f->getTranslation('nombre', $idioma)))->all(), 'o', $idioma),
            isset($valor['prendas']) => $this->unir(Prenda::withTrashed()->whereIn('id', $valor['prendas'])->get()
                ->map(fn ($p) => $p->getTranslation('nombre', $idioma))->all(), 'o', $idioma),
            isset($valor['n']) => $valor['n'].' '.$this->resolver($valor['de'], $idioma),
            isset($valor['lista']) => $this->unir(array_map(fn ($v) => $this->resolver($v, $idioma), $valor['lista']), $valor['union'] ?? 'y', $idioma),
            default => '',
        };
    }

    private function nombre(string $modelo, int $id, string $idioma): string
    {
        $clase = self::MODELOS[$modelo];
        $consulta = method_exists($clase, 'bootSoftDeletes') ? $clase::withTrashed() : $clase::query();

        return (string) $consulta->find($id)?->getTranslation('nombre', $idioma);
    }

    /** «a», «a y b», «a, b y c». */
    private function unir(array $partes, string $union, string $idioma): string
    {
        $ultimo = array_pop($partes);

        return $partes === [] ? (string) $ultimo : implode(', ', $partes).' '.__('precios.'.$union, [], $idioma).' '.$ultimo;
    }

    /** @return array{clave: string, datos: array<string, mixed>, cantidad: ?int} */
    public function aArray(): array
    {
        return ['clave' => $this->clave, 'datos' => $this->datos, 'cantidad' => $this->cantidad];
    }
}
