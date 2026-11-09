<?php

namespace App\Models;

use App\Dominio\Clientes\TipoDireccion;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dirección de facturación o de envío de un cliente (fase 5). */
class Direccion extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'direcciones';

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
        return ['tipo' => TipoDireccion::class, 'predeterminada' => 'boolean'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
