<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Variación de WooCommerce: una prenda en un color y una talla. */
class Variante extends Model
{
    use HasFactory, LogsActivity;

    /** A la venta: activa y con su color activo (lo único que ve la tienda). Las tallas no se desactivan. */
    public function scopeALaVenta(Builder $consulta): Builder
    {
        return $consulta->where('variantes.activa', true)
            ->whereHas('color', fn (Builder $q) => $q->where('activo', true));
    }

    protected $table = 'variantes';

    protected $guarded = ['id'];

    /** Al cambiar, se actualiza la fecha de la prenda (lastmod del sitemap). */
    protected $touches = ['prenda'];

    protected function casts(): array
    {
        return [
            'precio_base' => 'integer',
            'coste' => 'integer',
            'peso_gramos' => 'integer',
            'stock' => 'integer',
            'stock_minimo' => 'integer',
            'activa' => 'boolean',
        ];
    }

    /** @return BelongsTo<Prenda, $this> */
    public function prenda(): BelongsTo
    {
        return $this->belongsTo(Prenda::class);
    }

    /** @return BelongsTo<Color, $this> */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /** @return BelongsTo<Talla, $this> */
    public function talla(): BelongsTo
    {
        return $this->belongsTo(Talla::class);
    }

    /** Registro de actividad: cambios de variación. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalogo')
            ->logOnly(['sku', 'precio_base', 'coste', 'activa'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'Variación creada',
                'updated' => 'Variación modificada',
                'deleted' => 'Variación borrada',
                default => $evento,
            });
    }

    public function stockBajo(): bool
    {
        return $this->stock <= $this->stock_minimo;
    }
}
