<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prenda de un pack: unidades, colores permitidos y reparto de tallas (fijo) o tallas permitidas (libre).
 *
 * @property int $prenda_id
 * @property int $unidades
 * @property list<int> $colores
 * @property ?array<int, int> $reparto_tallas talla_id => unidades
 * @property ?list<int> $tallas_permitidas
 */
class LineaPack extends Model
{
    protected $table = 'lineas_pack';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['unidades' => 'integer', 'colores' => 'array', 'reparto_tallas' => 'array', 'tallas_permitidas' => 'array'];
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class);
    }

    /** @return BelongsTo<Prenda, $this> */
    public function prenda(): BelongsTo
    {
        return $this->belongsTo(Prenda::class);
    }
}
