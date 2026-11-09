<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $descripcion
 * @property int $cantidad
 * @property int $precio_unitario
 * @property ?int $tipo_iva
 * @property int $base
 */
class LineaFactura extends Model
{
    protected $table = 'lineas_factura';

    protected $guarded = ['id'];
}
