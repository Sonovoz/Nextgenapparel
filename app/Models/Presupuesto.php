<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Presupuesto en PDF (D187): copia de lo que había en el carrito y de lo que calculó el motor. Sin datos
 * personales: se abre con su enlace.
 *
 * @property int $id
 * @property string $uuid
 * @property string $numero
 * @property ?int $cliente_id
 * @property string $idioma
 * @property array{lineas: list<array{descripcion: string, detalle: string, unidades: int, neto: int}>, bruto: int, descuentos: int, descuento_nombre: ?string, base: int, impuestos: int, total: int} $contenido
 * @property list<array<string, mixed>> $origen
 * @property int $total
 * @property Carbon $valido_hasta
 * @property ?Carbon $convertido_en
 * @property Carbon $created_at
 * @property-read ?Cliente $cliente
 */
class Presupuesto extends Model
{
    protected $table = 'presupuestos';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vigente(): bool
    {
        return now()->lessThanOrEqualTo($this->valido_hasta);
    }

    protected function casts(): array
    {
        return ['contenido' => 'array', 'origen' => 'array', 'total' => 'integer', 'valido_hasta' => 'datetime', 'convertido_en' => 'datetime'];
    }
}
