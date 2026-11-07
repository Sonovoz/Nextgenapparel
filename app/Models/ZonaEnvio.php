<?php

namespace App\Models;

use App\Dominio\Precios\MetodoEnvio;
use App\Models\Concerns\Traducible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ajustes → Envío: zona (países y prefijos de código postal), método y tarifas. */
class ZonaEnvio extends Model
{
    use Traducible;

    protected $table = 'zonas_envio';

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['nombre'];

    protected function casts(): array
    {
        return [
            'paises' => 'array', 'prefijos_cp' => 'array', 'metodo' => MetodoEnvio::class, 'precio_kg_extra' => 'integer',
            'precio_caja' => 'integer', 'gratis_desde' => 'integer', 'plazo_dias' => 'integer', 'activa' => 'boolean',
        ];
    }

    public function tarifas(): HasMany
    {
        return $this->hasMany(TarifaEnvio::class)->orderBy('hasta_gramos');
    }
}
