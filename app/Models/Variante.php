<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Variación de WooCommerce: una prenda en un color y una talla. */
class Variante extends Model
{
    use HasFactory;

    protected $table = 'variantes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'precio_base' => 'integer',
            'coste' => 'integer',
            'peso_gramos' => 'integer',
            'stock' => 'integer',
            'stock_minimo' => 'integer',
            'activa' => 'boolean',
        ];
    }

    public function prenda(): BelongsTo
    {
        return $this->belongsTo(Prenda::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function talla(): BelongsTo
    {
        return $this->belongsTo(Talla::class);
    }

    public function stockBajo(): bool
    {
        return $this->stock <= $this->stock_minimo;
    }
}
