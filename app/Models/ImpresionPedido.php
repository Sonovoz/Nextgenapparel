<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpresionPedido extends Model
{
    protected $table = 'impresiones_pedido';

    protected $guarded = ['id'];

    protected $hidden = ['archivo', 'miniatura', 'sha256'];

    public function linea(): BelongsTo
    {
        return $this->belongsTo(LineaPedido::class, 'linea_pedido_id');
    }

    public function zonaEn(?string $idioma = null): string
    {
        $idioma ??= app()->getLocale();

        return (string) ($this->zona_nombre[$idioma] ?? $this->zona_nombre['es'] ?? reset($this->zona_nombre));
    }

    protected function casts(): array
    {
        return ['zona_nombre' => 'array', 'ancho_mm' => 'integer', 'alto_mm' => 'integer', 'desde_cuello_mm' => 'integer', 'desde_centro_mm' => 'integer'];
    }
}
