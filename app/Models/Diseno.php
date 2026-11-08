<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Diseño de un cliente (fase 4). El archivo está en el disco privado «disenos»;
 * el público solo conoce el `uuid`.
 */
class Diseno extends Model
{
    use HasUuids;

    protected $table = 'disenos';

    protected $guarded = ['id'];

    protected $hidden = ['propietario', 'ruta', 'miniatura', 'sha256'];

    /** El uuid es un identificador público aparte; la clave sigue siendo el id. */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return ['derechos_declarados_en' => 'datetime'];
    }
}
