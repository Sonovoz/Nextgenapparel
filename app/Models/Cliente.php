<?php

namespace App\Models;

use App\Dominio\Clientes\Nif;
use App\Dominio\Clientes\TipoCliente;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cliente de la tienda (fase 5, D153): con cuenta (user_id) o invitado. Los datos
 * fiscales (NIF, recargo de equivalencia) se piden al cerrar el pedido.
 */
class Cliente extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'clientes';

    protected $guarded = ['id'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoCliente::class,
            'recargo_equivalencia' => 'boolean',
            'activo' => 'boolean',
            'marketing_consentido_en' => 'datetime',
            'borrado_solicitado_en' => 'datetime',
        ];
    }

    /** Siempre en minúsculas y sin espacios: en MariaDB «A@x.com» y «a@x.com» chocan; en SQLite no. */
    protected function correo(): Attribute
    {
        return Attribute::set(fn (?string $valor) => $valor === null ? null : mb_strtolower(trim($valor)));
    }

    protected function nif(): Attribute
    {
        return Attribute::set(fn (?string $valor) => blank($valor) ? null : Nif::normalizar($valor));
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function direcciones(): HasMany
    {
        return $this->hasMany(Direccion::class);
    }

    public function disenos(): HasMany
    {
        return $this->hasMany(Diseno::class);
    }

    public function esInvitado(): bool
    {
        return $this->user_id === null;
    }

    /** ¿Tiene lo necesario para la factura? (se pide al cerrar el pedido). */
    public function datosFiscalesCompletos(): bool
    {
        return $this->nif !== null && Nif::valido($this->nif) && $this->direcciones()->where('tipo', 'facturacion')->exists();
    }
}
