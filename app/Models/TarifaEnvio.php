<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tramo de una zona de envío por peso: hasta tantos gramos, tanto (céntimos, sin IVA).
 *
 * @property int $zona_envio_id
 * @property int $hasta_gramos
 * @property int $precio
 */
class TarifaEnvio extends Model
{
    protected $table = 'tarifas_envio';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['hasta_gramos' => 'integer', 'precio' => 'integer'];
    }
}
