<?php

namespace App\Models;

use App\Dominio\Clientes\TipoCliente;
use App\Dominio\Pedidos\EstadoPedido;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Pedido (paso 6.1, D169): copia congelada de lo que el cliente confirmó. */
class Pedido extends Model
{
    use HasUuids;

    public const DISCO = 'pedidos';

    protected $table = 'pedidos';

    protected $guarded = ['id'];

    protected $hidden = ['ip'];

    protected static function booted(): void
    {
        // Al borrar el pedido se borra su carpeta de archivos, tras confirmar la transacción.
        static::deleted(fn (Pedido $pedido) => DB::afterCommit(fn () => Storage::disk(self::DISCO)->deleteDirectory($pedido->uuid)));
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(LineaPedido::class)->orderBy('orden');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialEstadoPedido::class)->orderBy('id');
    }

    public function correoConfirmado(): bool
    {
        return $this->correo_confirmado_en !== null;
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoPedido::class,
            'tipo_cliente' => TipoCliente::class,
            'recargo_equivalencia' => 'boolean',
            'direccion_envio' => 'array',
            'direccion_facturacion' => 'array',
            'desglose' => 'array',
            'correo_confirmado_en' => 'datetime',
            'condiciones_aceptadas_en' => 'datetime',
            'fecha_estimada_entrega' => 'date',
        ];
    }
}
