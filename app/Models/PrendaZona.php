<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Zona disponible en una prenda, con su precio y coste de impresión (céntimos).
 *
 * @property int $precio
 * @property int $coste
 */
class PrendaZona extends Pivot
{
    protected $table = 'prenda_zona';

    public $incrementing = true;
}
