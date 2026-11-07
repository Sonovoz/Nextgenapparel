<?php

namespace App\Models;

use App\Models\Concerns\Traducible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Producto variable: sus variaciones son las combinaciones color × talla. */
class Prenda extends Model
{
    use HasFactory, LogsActivity, SoftDeletes, Traducible;

    protected $table = 'prendas';

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['nombre', 'slug', 'descripcion', 'composicion', 'detalles', 'seo_titulo', 'seo_descripcion'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function familia(): BelongsTo
    {
        return $this->belongsTo(Familia::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function variantes(): HasMany
    {
        return $this->hasMany(Variante::class);
    }

    /** Zonas en las que se puede imprimir, con su precio y coste. */
    public function zonas(): BelongsToMany
    {
        return $this->belongsToMany(ZonaImpresion::class, 'prenda_zona')
            ->using(PrendaZona::class)
            ->withPivot(['id', 'precio', 'coste'])
            ->withTimestamps();
    }

    public function medidas(): HasMany
    {
        return $this->hasMany(MedidaTalla::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(Foto::class)->orderBy('orden');
    }

    /** Registro de actividad: cambios de prenda. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalogo')
            ->logOnly(['familia_id', 'activa'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'Prenda creada',
                'updated' => 'Prenda modificada',
                'deleted' => 'Prenda borrada',
                default => $evento,
            });
    }

    /** Precio más bajo de sus variaciones activas, en céntimos sin IVA (para «Desde…»). */
    public function precioDesde(): ?int
    {
        $minimo = $this->variantes()->aLaVenta()->min('precio_base');

        return $minimo === null ? null : (int) $minimo;
    }
}
