<?php

namespace App\Dominio\Pedidos;

use App\Models\Ajuste;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Fecha estimada de entrega (ficha § 8): plazo base de la familia más lenta del pedido
 * + días extra según las unidades (Ajustes → General) + plazo del transportista, contando
 * solo días laborables (de lunes a viernes). El equipo puede cambiarla en el pedido.
 */
final class PlazoEntrega
{
    /** @param  list<int>  $plazosFamilias  plazo base de cada familia del pedido */
    public static function fecha(array $plazosFamilias, int $unidades, int $diasTransportista, DateTimeInterface $desde): CarbonImmutable
    {
        $dias = max([0, ...$plazosFamilias]) + self::extraPorUnidades($unidades) + max(0, $diasTransportista);

        return self::sumarLaborables(CarbonImmutable::instance($desde)->startOfDay(), $dias);
    }

    public static function extraPorUnidades(int $unidades): int
    {
        $extra = 0;
        foreach ((array) Ajuste::obtener('pedidos.plazo_tramos') as $tramo) {
            if ($unidades >= (int) ($tramo['desde'] ?? PHP_INT_MAX)) {
                $extra = max($extra, (int) ($tramo['dias'] ?? 0));
            }
        }

        return $extra;
    }

    public static function sumarLaborables(CarbonImmutable $fecha, int $dias): CarbonImmutable
    {
        while ($dias > 0) {
            $fecha = $fecha->addDay();
            if (! $fecha->isWeekend()) {
                $dias--;
            }
        }

        return $fecha;
    }
}
