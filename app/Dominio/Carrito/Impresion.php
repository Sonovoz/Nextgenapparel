<?php

namespace App\Dominio\Carrito;

/**
 * Un diseño en una zona, tal como llega del configurador o del carrito: un logo subido
 * (`disenoId`) o un texto con su fuente y su tinta («Escribir mi marca»), con su tamaño
 * y su posición en mm (D99): el borde de arriba bajo el cuello y su centro desde el
 * centro de la prenda (negativo = a la izquierda de quien la lleva).
 */
final readonly class Impresion
{
    public const MINIMO_MM = 10;

    public const MAXIMO_POSICION_MM = 1000;

    public const MAXIMO_TEXTO = 60;

    /** Las mismas que el visor (public/js/visor.js: FUENTES y TINTAS). */
    public const FUENTES = 4;

    public const TINTAS = ['auto', 'negro', 'blanco', 'rojo', 'dorado'];

    /** Margen por el redondeo del visor al pasar de fracciones de la zona a mm. */
    public const TOLERANCIA_MM = 1;

    public function __construct(
        public int $zonaId,
        public ?int $disenoId,
        public int $anchoMm,
        public int $altoMm,
        public int $desdeCuelloMm,
        public int $desdeCentroMm,
        public ?string $texto = null,
        public ?int $fuente = null,
        public ?string $tinta = null,
    ) {}

    public function esTexto(): bool
    {
        return $this->texto !== null;
    }

    /** @return array<string, int|string|null> columnas de impresiones_carrito */
    public function columnas(): array
    {
        return [
            'zona_impresion_id' => $this->zonaId,
            'diseno_id' => $this->disenoId,
            'texto' => $this->texto,
            'fuente' => $this->fuente,
            'tinta' => $this->tinta,
            'ancho_mm' => $this->anchoMm,
            'alto_mm' => $this->altoMm,
            'desde_cuello_mm' => $this->desdeCuelloMm,
            'desde_centro_mm' => $this->desdeCentroMm,
        ];
    }
}
