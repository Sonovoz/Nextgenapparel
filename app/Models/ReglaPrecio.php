<?php

namespace App\Models;

use App\Dominio\Precios\Ambito;
use App\Dominio\Precios\AplicaA;
use App\Dominio\Precios\Efecto;
use App\Dominio\Precios\TipoRegla;
use App\Models\Concerns\Traducible;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Ofertas: tramos por cantidad y ofertas combinadas (excluyentes: se aplica la mejor). */
class ReglaPrecio extends Model
{
    use Traducible;

    protected $table = 'reglas_precio';

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['nombre'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoRegla::class, 'efecto' => Efecto::class, 'aplica_a' => AplicaA::class, 'ambito' => Ambito::class,
            'condiciones' => 'array', 'valor' => 'integer', 'activa' => 'boolean', 'desde' => 'datetime', 'hasta' => 'datetime',
        ];
    }

    public function scopeVigentes(Builder $consulta, \DateTimeInterface $ahora): Builder
    {
        return $consulta->where('activa', true)
            ->where(fn ($q) => $q->whereNull('desde')->orWhere('desde', '<=', $ahora))
            ->where(fn ($q) => $q->whereNull('hasta')->orWhere('hasta', '>=', $ahora));
    }
}
