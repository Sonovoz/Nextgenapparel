<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Devolución de dinero por la pasarela (paso 7.2, D182).
 *
 * @property int $id
 * @property int $pago_id
 * @property int $pedido_id
 * @property int $importe
 * @property string $clave
 * @property ?string $referencia
 * @property string $estado hecha | fallida | dudosa
 * @property string $motivo
 * @property ?string $error
 * @property int $intentos
 */
class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['importe' => 'integer', 'intentos' => 'integer'];
    }
}
