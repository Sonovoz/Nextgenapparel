<?php

namespace App\Models;

use App\Dominio\Precios\Ambito;
use App\Dominio\Precios\TipoMinimo;
use App\Models\Concerns\Traducible;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Pedido mínimo: unidades totales, por talla o importe, en un ámbito. */
class ReglaMinimo extends Model
{
    use Traducible;

    protected $table = 'reglas_minimo';

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['nombre'];

    protected function casts(): array
    {
        return ['tipo' => TipoMinimo::class, 'ambito' => Ambito::class, 'valor' => 'integer', 'activa' => 'boolean', 'desde' => 'datetime', 'hasta' => 'datetime'];
    }

    public function scopeVigentes(Builder $consulta, \DateTimeInterface $ahora): Builder
    {
        return $consulta->where('activa', true)
            ->where(fn ($q) => $q->whereNull('desde')->orWhere('desde', '<=', $ahora))
            ->where(fn ($q) => $q->whereNull('hasta')->orWhere('hasta', '>=', $ahora));
    }
}
