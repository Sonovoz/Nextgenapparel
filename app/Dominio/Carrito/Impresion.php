<?php

namespace App\Dominio\Carrito;

/**
 * Un diseño en una zona, tal como llega del configurador o del carrito: tamaño y
 * posición en mm desde el cuello y desde el centro (D99; negativo = a la izquierda
 * de quien la lleva).
 */
final readonly class Impresion
{
    public const MINIMO_MM = 10;

    public const MAXIMO_POSICION_MM = 1000;

    public function __construct(
        public int $zonaId,
        public ?int $disenoId,
        public int $anchoMm,
        public int $altoMm,
        public int $desdeCuelloMm,
        public int $desdeCentroMm,
    ) {}

    /** @return array<string, int|null> columnas de impresiones_carrito */
    public function columnas(): array
    {
        return [
            'zona_impresion_id' => $this->zonaId,
            'diseno_id' => $this->disenoId,
            'ancho_mm' => $this->anchoMm,
            'alto_mm' => $this->altoMm,
            'desde_cuello_mm' => $this->desdeCuelloMm,
            'desde_centro_mm' => $this->desdeCentroMm,
        ];
    }
}
