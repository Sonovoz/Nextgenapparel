<?php

namespace App\Models;

use App\Dominio\Carrito\TipoLinea;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una línea del carrito: una prenda en un color con sus tallas, o un pack con sus elecciones.
 *
 * @property TipoLinea $tipo
 * @property ?int $prenda_id
 * @property ?int $color_id
 * @property ?int $pack_id
 * @property ?string $coleccion nombre que el cliente da a su colección (D220)
 * @property ?array<int, array{color?: int, tallas?: array<int, int>}> $elecciones linea_pack_id => elección
 */
class LineaCarrito extends Model
{
    use HasUuids;

    protected $table = 'lineas_carrito';

    protected $guarded = ['id'];

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
        return ['tipo' => TipoLinea::class, 'tallas' => 'array', 'elecciones' => 'array'];
    }

    public function carrito(): BelongsTo
    {
        return $this->belongsTo(Carrito::class);
    }

    /** @return BelongsTo<Prenda, $this> */
    public function prenda(): BelongsTo
    {
        return $this->belongsTo(Prenda::class)->withTrashed();
    }

    /** @return BelongsTo<Color, $this> */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /** @return BelongsTo<Pack, $this> */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class)->withTrashed();
    }

    /** @return HasMany<ImpresionCarrito, $this> */
    public function impresiones(): HasMany
    {
        return $this->hasMany(ImpresionCarrito::class)->orderBy('zona_impresion_id');
    }

    /** @return array<int, int> talla_id => unidades, solo las que tienen unidades */
    public function unidadesPorTalla(): array
    {
        return array_filter(array_map('intval', $this->tallas ?? []), fn (int $u) => $u > 0);
    }
}
