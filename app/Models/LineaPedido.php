<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LineaPedido extends Model
{
    use HasUuids;

    protected $table = 'lineas_pedido';

    protected $guarded = ['id'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function prenda(): BelongsTo
    {
        return $this->belongsTo(Prenda::class);
    }

    public function impresiones(): HasMany
    {
        return $this->hasMany(ImpresionPedido::class)->orderBy('id');
    }

    public function maquetas(): HasMany
    {
        return $this->hasMany(MaquetaPedido::class)->orderByRaw("cara = 'delantera' desc");
    }

    /** El nombre copiado al confirmar, en ese idioma (o en español). */
    public function nombreEn(?string $idioma = null): string
    {
        $idioma ??= app()->getLocale();

        return (string) ($this->nombre[$idioma] ?? $this->nombre['es'] ?? reset($this->nombre));
    }

    public function colorEn(?string $idioma = null): ?string
    {
        $idioma ??= app()->getLocale();

        return $this->color_nombre === null ? null : (string) ($this->color_nombre[$idioma] ?? $this->color_nombre['es'] ?? reset($this->color_nombre));
    }

    protected function casts(): array
    {
        return ['nombre' => 'array', 'color_nombre' => 'array', 'tallas' => 'array', 'elecciones' => 'array'];
    }
}
