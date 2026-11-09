<?php

namespace App\Dominio\Carrito;

use App\Dominio\Disenos\NivelCalidad;
use App\Dominio\Precios\Motor\Resultado;
use App\Models\LineaCarrito;

/**
 * El carrito valorado: cada línea con lo que impide comprarla y la calidad de sus
 * diseños, y el resultado del motor con las líneas que sí se pueden comprar.
 */
final readonly class Valoracion
{
    /**
     * @param  list<array{linea: LineaCarrito, problemas: list<string>, calidades: array<int, array{ppp: int, nivel: NivelCalidad}>}>  $lineas  calidades por zona
     */
    public function __construct(public array $lineas, public Resultado $resultado) {}

    public function vacio(): bool
    {
        return $this->lineas === [];
    }

    public function conProblemas(): bool
    {
        foreach ($this->lineas as $linea) {
            if ($linea['problemas'] !== []) {
                return true;
            }
        }

        return false;
    }

    /** Se puede cerrar el pedido: todas las líneas se pueden comprar y el motor no ve impedimentos. */
    public function confirmable(): bool
    {
        return ! $this->vacio() && ! $this->conProblemas() && $this->resultado->confirmable();
    }

    /** @return list<string> */
    public function problemas(LineaCarrito $linea, ?string $idioma = null): array
    {
        foreach ($this->lineas as $valorada) {
            if ($valorada['linea']->is($linea)) {
                return array_map(fn (string $p) => __('carrito.'.$p, ['maximo' => EditarCarrito::MAXIMO_LINEAS], $idioma), $valorada['problemas']);
            }
        }

        return [];
    }
}
