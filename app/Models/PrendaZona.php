<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/** Zona disponible en una prenda, con su precio y coste de impresión (céntimos). */
class PrendaZona extends Pivot
{
    protected $table = 'prenda_zona';

    public $incrementing = true;

    protected function casts(): array
    {
        return ['precio' => 'integer', 'coste' => 'integer'];
    }
}
