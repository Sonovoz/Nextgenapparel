<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Maqueta de una cara de una línea del pedido (paso 6.2, D98), en el disco privado «pedidos».
 *
 * @property int $id
 * @property int $linea_pedido_id
 * @property string $cara
 * @property string $ruta
 * @property int $ancho_px
 * @property int $alto_px
 * @property-read ?LineaPedido $linea
 */
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
