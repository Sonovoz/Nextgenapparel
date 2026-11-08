<?php

namespace App\Dominio\Precios\Motor;

/**
 * Resultado del motor de precios: cada importe en céntimos y cada paso del
 * cálculo, que el pedido guardará en desglose_calculo.
 */
final class Resultado
{
    /**
     * @param  list<LineaCalculada>  $lineas
     * @param  list<Mensaje>  $avisos  ofertas aplicadas, cercanas, cupón…
     * @param  list<Mensaje>  $impedimentos  lo que impide confirmar (mínimos, reparto, destino…)
     * @param  list<array{tipo_iva: int, recargo: int, base: int, cuota: int, cuota_recargo: int}>  $impuestos
     * @param  array{zona: string, metodo: string, importe: int, plazo_dias: int, peso_gramos: int, bultos: ?int}|null  $envio
     * @param  array{id: int, nombre: string, importe: int}|null  $oferta
     * @param  array{codigo: string, importe: int}|null  $cupon
     */
    public function __construct(
        public readonly array $lineas,
        public readonly ?array $oferta,
        public readonly ?array $cupon,
        public readonly ?array $envio,
        public readonly array $impuestos,
        public readonly array $avisos,
        public readonly array $impedimentos,
    ) {}

    public function unidades(): int
    {
        return array_sum(array_map(fn (LineaCalculada $l) => $l->unidades, $this->lineas));
    }

    public function bruto(): int
    {
        return array_sum(array_map(fn (LineaCalculada $l) => $l->bruto(), $this->lineas));
    }

    public function descuentos(): int
    {
        return array_sum(array_map(fn (LineaCalculada $l) => $l->descuento + $l->cupon, $this->lineas));
    }

    /** Mercancía después de descuentos, sin envío ni impuestos. */
    public function subtotal(): int
    {
        return array_sum(array_map(fn (LineaCalculada $l) => $l->neto(), $this->lineas));
    }

    public function envioImporte(): int
    {
        return $this->envio['importe'] ?? 0;
    }

    public function baseImponible(): int
    {
        return $this->subtotal() + $this->envioImporte();
    }

    public function totalImpuestos(): int
    {
        return array_sum(array_map(fn (array $i) => $i['cuota'] + $i['cuota_recargo'], $this->impuestos));
    }

    public function total(): int
    {
        return $this->baseImponible() + $this->totalImpuestos();
    }

    public function confirmable(): bool
    {
        return $this->impedimentos === [] && $this->lineas !== [];
    }

    /** @return list<string> */
    public function textos(string $tipo = 'avisos', ?string $idioma = null): array
    {
        return array_map(fn (Mensaje $m) => $m->texto($idioma), $tipo === 'avisos' ? $this->avisos : $this->impedimentos);
    }

    /** Para guardar en el pedido (desglose_calculo) y mostrar en el backend. */
    public function aArray(): array
    {
        return [
            'lineas' => array_map(fn (LineaCalculada $l) => $l->aArray(), $this->lineas),
            'pasos' => [
                ['paso' => 'prendas', 'importe' => array_sum(array_map(fn ($l) => $l->prendas, $this->lineas))],
                ['paso' => 'impresion', 'importe' => array_sum(array_map(fn ($l) => $l->impresion, $this->lineas))],
                ['paso' => 'oferta', 'importe' => -($this->oferta['importe'] ?? 0), 'detalle' => $this->oferta],
                ['paso' => 'cupon', 'importe' => -($this->cupon['importe'] ?? 0), 'detalle' => $this->cupon],
                ['paso' => 'envio', 'importe' => $this->envioImporte(), 'detalle' => $this->envio],
                ['paso' => 'impuestos', 'importe' => $this->totalImpuestos(), 'detalle' => $this->impuestos],
            ],
            'subtotal' => $this->subtotal(),
            'base_imponible' => $this->baseImponible(),
            'total' => $this->total(),
            'confirmable' => $this->confirmable(),
            'avisos' => array_map(fn (Mensaje $m) => $m->aArray(), $this->avisos),
            'impedimentos' => array_map(fn (Mensaje $m) => $m->aArray(), $this->impedimentos),
        ];
    }
}
