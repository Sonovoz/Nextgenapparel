<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un correo comercial enviado (v2, D190).
 *
 * @property string $tipo nueva_compra | reactivacion | campana
 * @property int $cliente_id
 * @property ?int $pedido_id
 * @property ?int $campana_id
 * @property Carbon $enviado_en
 */
class EnvioCampana extends Model
{
    protected $table = 'envios_campana';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enviado_en' => 'datetime'];
    }
}
