<?php

namespace App\Dominio\Pedidos;

/**
 * Estados del pedido (ficha § 8). El nombre que ve el cliente sale de lang/{es,en}/pedidos.php
 * hasta que el paso 6.4 lo haga editable en el backend.
 */
enum EstadoPedido: string
{
    case PendientePago = 'pendiente_pago';
    case Pagado = 'pagado';
    case DisenoEnRevision = 'diseno_en_revision';
    case RequiereCambios = 'requiere_cambios';
    case EnProduccion = 'en_produccion';
    case Enviado = 'enviado';
    case Entregado = 'entregado';
    case Cancelado = 'cancelado';
    case Reembolsado = 'reembolsado';

    /** Nombre interno, para el backend (solo en español, D33). */
    public function etiqueta(): string
    {
        return match ($this) {
            self::PendientePago => 'Pendiente de pago',
            self::Pagado => 'Pagado',
            self::DisenoEnRevision => 'Diseño en revisión',
            self::RequiereCambios => 'Requiere cambios',
            self::EnProduccion => 'En producción',
            self::Enviado => 'Enviado',
            self::Entregado => 'Entregado',
            self::Cancelado => 'Cancelado',
            self::Reembolsado => 'Reembolsado',
        };
    }

    /** Lo que ve el cliente, en su idioma. */
    public function visible(?string $idioma = null): string
    {
        return __('pedidos.estado_'.$this->value, [], $idioma);
    }

    /** Un pedido cancelado o reembolsado no cuenta como uso de un cupón. */
    public function cuentaComoUso(): bool
    {
        return ! in_array($this, [self::Cancelado, self::Reembolsado], true);
    }
}
