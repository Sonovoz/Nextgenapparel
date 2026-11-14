<?php

namespace App\Models;

use App\Dominio\Clientes\TipoCliente;
use App\Dominio\Pedidos\EstadoPedido;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Pedido (paso 6.1, D169): copia congelada de lo que el cliente confirmó.
 *
 * @property int $id
 * @property string $uuid
 * @property ?string $numero
 * @property ?int $cliente_id
 * @property EstadoPedido $estado
 * @property ?Carbon $visto_en primera vez que el equipo abrió el pedido (D252)
 * @property string $idioma
 * @property string $correo
 * @property ?Carbon $correo_confirmado_en
 * @property TipoCliente $tipo_cliente
 * @property string $nombre_fiscal
 * @property ?string $nif
 * @property bool $recargo_equivalencia
 * @property array<string, ?string> $direccion_envio
 * @property array<string, ?string> $direccion_facturacion
 * @property ?string $cupon
 * @property int $bruto
 * @property int $descuentos
 * @property int $subtotal
 * @property int $envio
 * @property int $base_imponible
 * @property int $impuestos
 * @property int $total
 * @property array<string, mixed> $desglose
 * @property ?Carbon $fecha_estimada_entrega
 * @property ?string $transportista
 * @property ?CarbonInterface $archivos_borrados_en archivos de impresión borrados (D230)
 * @property ?string $url_seguimiento enlace de seguimiento del transportista (D226)
 * @property ?string $numero_seguimiento
 * @property ?Carbon $cancelacion_solicitada_en
 * @property ?string $motivo_cancelacion
 * @property-read ?Cliente $cliente
 * @property-read Collection<int, LineaPedido> $lineas
 * @property-read Collection<int, Factura> $facturas
 * @property-read Collection<int, Incidencia> $incidencias
 */
class Pedido extends Model
{
    use HasFactory, HasUuids;

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

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class)->orderBy('id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(NotaPedido::class)->latest('id');
    }

    /** @return HasMany<Incidencia, $this> */
    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class)->orderBy('id');
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
            'visto_en' => 'datetime',
            'archivos_borrados_en' => 'datetime',
            'condiciones_aceptadas_en' => 'datetime',
            'fecha_estimada_entrega' => 'date',
            'cancelacion_solicitada_en' => 'datetime',
        ];
    }
}
