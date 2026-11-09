<?php

namespace App\Models;

use App\Dominio\Pedidos\EstadoPedido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialEstadoPedido extends Model
{
    protected $table = 'historial_estados_pedido';

    protected $guarded = ['id'];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return ['estado' => EstadoPedido::class, 'notificado_cliente' => 'boolean'];
    }
}
