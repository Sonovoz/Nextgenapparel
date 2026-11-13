<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Disenos\LimpiarDisenos;
use App\Dominio\Sistema\Salud\Latido;
use App\Dominio\Sistema\Salud\Resultado;
use Throwable;

/**
 * D231: las limpiezas automáticas están al día: la de diseños sin venta (cada hora) y la de archivos de
 * pedidos (cada noche). Si no se ejecutan, los archivos se acumulan hasta llenar el disco.
 */
class Limpieza implements Comprobacion
{
    public const HORAS_DISENOS = 3;

    public const HORAS_PEDIDOS = 26;

    public function clave(): string
    {
        return 'limpieza';
    }

    public function nombre(): string
    {
        return 'Limpieza de archivos';
    }

    public function comprobar(): array
    {
        $disenos = Latido::ultimo(Latido::LIMPIEZA_DISENOS);
        $pedidos = Latido::ultimo(Latido::LIMPIEZA_PEDIDOS);
        $problemas = [];
        if ($disenos === null || $disenos->lt(now()->subHours(self::HORAS_DISENOS))) {
            $problemas[] = 'La de diseños sin venta no se ejecuta desde '.($disenos?->format('d/m/Y H:i') ?? 'nunca').'.';
        }
        if ($pedidos === null || $pedidos->lt(now()->subHours(self::HORAS_PEDIDOS))) {
            $problemas[] = 'La de archivos de pedidos no se ejecuta desde '.($pedidos?->format('d/m/Y H:i') ?? 'nunca').'.';
        }
        try {
            $pendientes = app(LimpiarDisenos::class)->disenosCaducados()->count();
        } catch (Throwable) {
            $pendientes = 0;
        }

        if ($problemas !== []) {
            return Resultado::aviso(implode(' ', $problemas).($pendientes > 0 ? " Hay {$pendientes} diseños sin venta esperando a borrarse." : ''));
        }

        return Resultado::correcto('Al día. Última de diseños: '.$disenos?->format('H:i').'; de pedidos: '.$pedidos?->format('d/m H:i').'.');
    }
}
