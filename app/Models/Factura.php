<?php

namespace App\Models;

use App\Dominio\Facturacion\TipoFactura;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/** Factura (paso 6.5a, D175). Inmutable: no se edita ni se borra; se corrige con una rectificativa.
 *
 * @property int $id
 * @property string $uuid
 * @property string $codigo
 * @property TipoFactura $tipo
 * @property int $pedido_id
 * @property ?int $rectifica_id
 * @property ?string $motivo
 * @property Carbon $emitida_en
 * @property string $idioma
 * @property array<string, ?string> $emisor
 * @property array<string, mixed> $destinatario
 * @property int $base_imponible
 * @property int $iva
 * @property int $recargo
 * @property int $total
 * @property list<array{tipo_iva: int, recargo: int, base: int, cuota: int, cuota_recargo: int}> $desglose
 * @property bool $verifactu
 * @property string $huella
 * @property ?string $huella_anterior
 * @property string $qr_url
 * @property string $aeat_estado
 * @property-read Pedido $pedido
 * @property-read ?Factura $rectifica
 * @property-read Collection<int, LineaFactura> $lineas
 */
class Factura extends Model
{
    use HasUuids;

    protected $table = 'facturas';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Una factura no se modifica: se rectifica.'));
        static::deleting(fn () => throw new LogicException('Una factura no se borra: se rectifica.'));
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function rectifica(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rectifica_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(LineaFactura::class)->orderBy('orden');
    }

    public function nombreArchivo(): string
    {
        return 'factura-'.$this->codigo.'.pdf';
    }

    protected function casts(): array
    {
        return ['tipo' => TipoFactura::class, 'emitida_en' => 'datetime', 'emisor' => 'array', 'destinatario' => 'array', 'desglose' => 'array', 'verifactu' => 'boolean'];
    }
}
