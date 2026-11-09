<?php

namespace App\Dominio\Precios\Motor;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Lo que el cliente quiere comprar, tal cual llega del configurador, del
 * carrito o del simulador del backend. El motor no se fía de ningún precio
 * de aquí: todos salen de la base de datos.
 */
final class Cesta
{
    /**
     * @param  list<LineaCesta>  $lineas
     * @param  list<PackCesta>  $packs
     * @param  list<string>  $cuponesUsados  códigos que este cliente ya usó (los da el pedido, fase 6)
     */
    public function __construct(
        public readonly array $lineas = [],
        public readonly array $packs = [],
        public readonly ?string $cupon = null,
        public readonly bool $recargoEquivalencia = false,
        public readonly ?Destino $destino = null,
        public readonly array $cuponesUsados = [],
        public readonly ?DateTimeInterface $ahora = null,
        /** Grupo de cliente con tarifa (v2, D192), si el cliente es de uno activo. */
        public readonly ?int $grupoId = null,
    ) {}

    public function ahora(): DateTimeInterface
    {
        return $this->ahora ?? new DateTimeImmutable;
    }
}
