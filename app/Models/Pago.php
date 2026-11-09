<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un intento de cobro de un pedido en la pasarela (fase 7, D181).
 *
 * @property int $id
 * @property int $pedido_id
 * @property string $pasarela
 * @property ?string $metodo
 * @property string $referencia
 * @property int $importe
 * @property string $estado pendiente | cobrado | rechazado
 * @property ?string $error
 * @property bool $pruebas
 * @property ?Carbon $cobrado_en
 * @property-read Pedido $pedido
 */
class Pago extends Model
{
    protected $table = 'pagos';

    protected $guarded = ['id'];

    /** @return BelongsTo<Pedido, $this> */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    protected function casts(): array
    {
        return ['importe' => 'integer', 'pruebas' => 'boolean', 'cobrado_en' => 'datetime'];
    }
}
