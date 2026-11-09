<?php

namespace App\Models;

use App\Dominio\Incidencias\MotivoIncidencia;
use App\Dominio\Incidencias\ResolucionIncidencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Incidencia de un pedido entregado, con fotos (paso 6.6b, D178). Se resuelve una vez y no cambia.
 *
 * @property int $id
 * @property string $uuid
 * @property string $codigo
 * @property int $pedido_id
 * @property MotivoIncidencia $motivo
 * @property string $descripcion
 * @property string $estado abierta | resuelta
 * @property ?ResolucionIncidencia $resolucion
 * @property ?int $importe_reembolso céntimos, con impuestos
 * @property ?int $factura_id
 * @property ?string $respuesta
 * @property ?int $user_id
 * @property ?Carbon $resuelta_en
 * @property Carbon $created_at
 * @property-read Pedido $pedido
 * @property-read ?Factura $factura
 * @property-read ?User $usuario
 * @property-read Collection<int, FotoIncidencia> $fotos
 */
class Incidencia extends Model
{
    protected $table = 'incidencias';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Pedido, $this> */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /** @return BelongsTo<Factura, $this> */
    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<FotoIncidencia, $this> */
    public function fotos(): HasMany
    {
        return $this->hasMany(FotoIncidencia::class)->orderBy('id');
    }

    public function abierta(): bool
    {
        return $this->estado === 'abierta';
    }

    protected function casts(): array
    {
        return ['motivo' => MotivoIncidencia::class, 'resolucion' => ResolucionIncidencia::class, 'importe_reembolso' => 'integer', 'resuelta_en' => 'datetime'];
    }
}
