<?php

namespace App\Dominio\Disenos;

/** Resultado del análisis de un PNG en el servidor (AnalizadorPng). */
final readonly class AnalisisPng
{
    public function __construct(
        public int $anchoPx,
        public int $altoPx,
        /** Lo que se imprime: el rectángulo sin márgenes transparentes (D114). */
        public int $utilX,
        public int $utilY,
        public int $utilAnchoPx,
        public int $utilAltoPx,
        /** transparente | fondo | fondo_blanco (mismo criterio que el visor). */
        public string $fondo,
        public ?string $perfilColor,
        public int $profundidadBits,
        /** La resolución que declara el archivo (pHYs), en ppp; null si no la declara (D206). */
        public ?int $pppArchivo = null,
    ) {}

    /** @return array<string, int|string|null> columnas de la tabla disenos */
    public function aColumnas(): array
    {
        return [
            'ancho_px' => $this->anchoPx,
            'alto_px' => $this->altoPx,
            'util_x' => $this->utilX,
            'util_y' => $this->utilY,
            'util_ancho_px' => $this->utilAnchoPx,
            'util_alto_px' => $this->utilAltoPx,
            'fondo' => $this->fondo,
            'perfil_color' => $this->perfilColor,
            'profundidad_bits' => $this->profundidadBits,
            'ppp_archivo' => $this->pppArchivo,
        ];
    }
}
