<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lo que gasta una respuesta de la IA (v2, D194).
 *
 * @property ?int $cliente_id
 * @property string $uso venta | seo
 * @property int $tokens_entrada
 * @property int $tokens_salida
 * @property int $coste milésimas de céntimo
 */
class ConsumoIa extends Model
{
    protected $table = 'consumos_ia';

    protected $guarded = ['id'];
}
