<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cajas de envío: cuántas prendas y cuánto peso caben. */
class Caja extends Model
{
    protected $table = 'cajas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['capacidad_prendas' => 'integer', 'peso_max_gramos' => 'integer', 'activa' => 'boolean'];
    }
}
