<?php

namespace App\Models;

use App\Dominio\Catalogo\Origen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Proveedor de prendas lisas; su API, si la tiene, se conecta más adelante (D13). */
class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo_conector' => Origen::class,
            'configuracion' => 'encrypted:array', // claves de API cifradas
            'activo' => 'boolean',
        ];
    }

    public function prendas(): HasMany
    {
        return $this->hasMany(Prenda::class);
    }
}
