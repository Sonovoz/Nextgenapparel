<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Carrito persistente (paso 5.5): de un cliente o, sin cuenta, del token de una cookie
 * (solo se guarda su hash). No guarda precios: los calcula el motor cada vez (regla 3).
 */
class Carrito extends Model
{
    use HasUuids;

    protected $table = 'carritos';

    protected $guarded = ['id'];

    protected $hidden = ['token'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** @return HasMany<LineaCarrito, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaCarrito::class)->orderBy('orden')->orderBy('id');
    }
}
