<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un aviso de carrito abandonado enviado (v2, D189).
 *
 * @property int $id
 * @property int $carrito_id
 * @property int $cliente_id
 * @property int $paso
 * @property ?string $cupon
 * @property Carbon $enviado_en
 */
class AvisoCarrito extends Model
{
    protected $table = 'avisos_carrito';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['paso' => 'integer', 'enviado_en' => 'datetime'];
    }
}
