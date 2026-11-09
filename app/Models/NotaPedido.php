<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nota del pedido (paso 6.3): «privada» (solo el equipo) o «cliente» (la ve en su pedido). */
class NotaPedido extends Model
{
    protected $table = 'notas_pedido';

    protected $guarded = ['id'];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
