<?php

namespace App\Dominio\Precios\Motor;

/** Una línea ya valorada por el motor (céntimos sin IVA). */
final class LineaCalculada
{
    public int $descuento = 0;

    public int $cupon = 0;

    /**
     * @param  array<int, int>  $tallas
     */
    public function __construct(
        public readonly string $tipo,          // prenda | pack
        public readonly int $referencia,       // prenda_id o pack_id
        public readonly string $nombre,
        public readonly ?int $familiaId,
        public readonly int $unidades,
        public readonly array $tallas,
        public readonly int $prendas,          // importe de las prendas
        public readonly int $impresion,        // importe de la impresión
        public readonly int $tipoIva,
        public readonly int $recargo,
        public readonly int $pesoGramos,
        public readonly bool $admiteDescuentos,
    ) {}

    public function bruto(): int
    {
        return $this->prendas + $this->impresion;
    }

    public function neto(): int
    {
        return $this->bruto() - $this->descuento - $this->cupon;
    }

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return [
            'tipo' => $this->tipo, 'referencia' => $this->referencia, 'nombre' => $this->nombre, 'unidades' => $this->unidades,
            'tallas' => $this->tallas, 'prendas' => $this->prendas, 'impresion' => $this->impresion, 'descuento' => $this->descuento,
            'cupon' => $this->cupon, 'neto' => $this->neto(), 'tipo_iva' => $this->tipoIva, 'recargo' => $this->recargo,
        ];
    }
}
