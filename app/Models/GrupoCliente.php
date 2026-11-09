<?php

namespace App\Models;

use App\Models\Concerns\Traducible;
use Illuminate\Database\Eloquent\Model;

/**
 * Grupo de clientes con su tarifa (v2, D192): un descuento % sobre prendas e impresión (no packs).
 *
 * @property int $id
 * @property int $descuento centésimas de %
 * @property bool $activo
 */
class GrupoCliente extends Model
{
    use Traducible;

    /** @var list<string> */
    public array $translatable = ['nombre'];

    protected $table = 'grupos_cliente';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['descuento' => 'integer', 'activo' => 'boolean'];
    }
}
