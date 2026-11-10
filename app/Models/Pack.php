<?php

namespace App\Models;

use App\Dominio\Catalogo\ModoCliente;
use App\Dominio\Precios\RepartoPack;
use App\Models\Concerns\Traducible;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Productos → Packs: oferta cerrada con precio fijo (ficha § 6 bis, D105–D107).
 *
 * @property RepartoPack $reparto
 * @property ?list<string> $modos claves de ModoCliente; null o vacío: genérico (D203)
 */
class Pack extends Model
{
    use LogsActivity, SoftDeletes, Traducible;

    protected $table = 'packs';

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['nombre', 'slug', 'descripcion', 'seo_titulo', 'seo_descripcion'];

    protected function casts(): array
    {
        return ['precio' => 'integer', 'reparto' => RepartoPack::class, 'activo' => 'boolean', 'desde' => 'datetime', 'hasta' => 'datetime', 'modos' => 'array'];
    }

    /** @return HasMany<LineaPack, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaPack::class);
    }

    public function zonas(): BelongsToMany
    {
        return $this->belongsToMany(ZonaImpresion::class, 'pack_zona');
    }

    public function claseImpuesto(): BelongsTo
    {
        return $this->belongsTo(ClaseImpuesto::class);
    }

    public function unidades(): int
    {
        return (int) $this->lineas->sum('unidades');
    }

    /** ¿Sale en este modo? Sin modos marcados, el pack es genérico y sale en todos (D203). */
    public function esPara(ModoCliente $modo): bool
    {
        return $this->modos === null || $this->modos === [] || in_array($modo->value, $this->modos, true);
    }

    public function scopeVigentes(Builder $consulta, \DateTimeInterface $ahora): Builder
    {
        return $consulta->where('activo', true)
            ->where(fn ($q) => $q->whereNull('desde')->orWhere('desde', '<=', $ahora))
            ->where(fn ($q) => $q->whereNull('hasta')->orWhere('hasta', '>=', $ahora));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['precio', 'activo', 'reparto', 'desde', 'hasta'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
