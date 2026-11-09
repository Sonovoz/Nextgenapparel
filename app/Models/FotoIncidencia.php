<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto de una incidencia, en el disco privado de los pedidos, sin datos de la cámara (D178).
 *
 * @property int $id
 * @property int $incidencia_id
 * @property string $ruta
 * @property int $ancho_px
 * @property int $alto_px
 * @property-read Incidencia $incidencia
 */
class FotoIncidencia extends Model
{
    protected $table = 'fotos_incidencia';

    protected $guarded = ['id'];

    /** @return BelongsTo<Incidencia, $this> */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }
}
