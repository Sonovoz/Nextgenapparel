<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Medidas de una talla de una prenda (guía de tallas), en milímetros. */
class MedidaTalla extends Model
{
    protected $table = 'medidas_talla';

    protected $guarded = ['id'];

    public function prenda(): BelongsTo
    {
        return $this->belongsTo(Prenda::class);
    }

    public function talla(): BelongsTo
    {
        return $this->belongsTo(Talla::class);
    }
}
