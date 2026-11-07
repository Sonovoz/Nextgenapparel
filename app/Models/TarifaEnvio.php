<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarifaEnvio extends Model
{
    protected $table = 'tarifas_envio';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['hasta_gramos' => 'integer', 'precio' => 'integer'];
    }
}
