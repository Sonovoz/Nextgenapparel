<?php

namespace App\Dominio\Pedidos;

use App\Models\Cliente;
use App\Models\Pedido;

/**
 * Los cupones que ya usó este cliente (por su ficha o por su correo) en pedidos que
 * cuentan: los cancelados y reembolsados no. El cierre y el pedido usan esta misma
 * lista, así el total que ve el cliente es el que se cobra (D169).
 */
final class CuponesUsados
{
    /** @return list<string> */
    public static function de(?Cliente $cliente, ?string $correo): array
    {
        $correo = $correo === null ? null : mb_strtolower(trim($correo));
        if ($cliente === null && blank($correo)) {
            return [];
        }

        return Pedido::query()
            ->where(fn ($q) => $q->when(filled($correo), fn ($q) => $q->where('correo', $correo))->when($cliente, fn ($q) => $q->orWhere('cliente_id', $cliente->id)))
            ->whereNotNull('cupon')
            ->whereNotIn('estado', [EstadoPedido::Cancelado->value, EstadoPedido::Reembolsado->value])
            ->pluck('cupon')->unique()->values()->all();
    }
}
