<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un diseño en una zona de una línea del carrito, con su tamaño y su posición en mm (D99). */
class ImpresionCarrito extends Model
{
    protected $table = 'impresiones_carrito';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ancho_mm' => 'integer', 'alto_mm' => 'integer', 'desde_cuello_mm' => 'integer', 'desde_centro_mm' => 'integer'];
    }

    public function linea(): BelongsTo
    {
        return $this->belongsTo(LineaCarrito::class, 'linea_carrito_id');
    }

    /** @return BelongsTo<ZonaImpresion, $this> */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaImpresion::class, 'zona_impresion_id');
    }

    /** @return BelongsTo<Diseno, $this> */
    public function diseno(): BelongsTo
    {
        return $this->belongsTo(Diseno::class);
    }
}
