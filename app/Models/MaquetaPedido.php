<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Maqueta de una cara de una línea del pedido (paso 6.2, D98), en el disco privado «pedidos». */
class MaquetaPedido extends Model
{
    protected $table = 'maquetas_pedido';

    protected $guarded = ['id'];

    protected $hidden = ['ruta'];

    public function linea(): BelongsTo
    {
        return $this->belongsTo(LineaPedido::class, 'linea_pedido_id');
    }

    protected function casts(): array
    {
        return ['generada_en' => 'datetime'];
    }
}
