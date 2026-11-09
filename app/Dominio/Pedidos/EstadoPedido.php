<?php

namespace App\Dominio\Pedidos;

use App\Models\Ajuste;

/**
 * Estados del pedido (ficha § 8). El nombre que ve el cliente se edita en Ajustes → Correos (paso 6.4).
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

    /** Lo que ve el cliente, en su idioma: el nombre de Ajustes → Correos o, si está vacío, el de lang/. */
    public function visible(?string $idioma = null): string
    {
        $idioma ??= app()->getLocale();
        $propio = trim((string) (((array) Ajuste::obtener('pedidos.estados_visibles'))[$this->value][$idioma] ?? ''));

        return $propio !== '' ? $propio : __('pedidos.estado_'.$this->value, [], $idioma);
    }

    /**
     * Los cambios que puede hacer el equipo (ficha § 8). Se cancela hasta que empieza la
     * producción; «Reembolsado» llega con los pagos (fase 7).
     *
     * @return list<self>
     */
    public function siguientes(): array
    {
        return match ($this) {
            self::PendientePago => [self::Pagado, self::Cancelado],
            self::Pagado => [self::DisenoEnRevision, self::EnProduccion, self::Cancelado],
            self::DisenoEnRevision => [self::RequiereCambios, self::EnProduccion, self::Cancelado],
            self::RequiereCambios => [self::DisenoEnRevision, self::Cancelado],
            self::EnProduccion => [self::Enviado],
            self::Enviado => [self::Entregado],
            self::Entregado, self::Cancelado, self::Reembolsado => [],
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendientePago => 'gray',
            self::Pagado, self::DisenoEnRevision => 'info',
            self::RequiereCambios => 'warning',
            self::EnProduccion, self::Enviado => 'primary',
            self::Entregado => 'success',
            self::Cancelado, self::Reembolsado => 'danger',
        };
    }

    /** Un pedido cancelado o reembolsado no cuenta como uso de un cupón. */
    public function cuentaComoUso(): bool
    {
        return ! in_array($this, [self::Cancelado, self::Reembolsado], true);
    }
}
