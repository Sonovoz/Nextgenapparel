<?php

namespace App\Models;

use App\Dominio\Precios\MetodoEnvio;
use App\Models\Concerns\Traducible;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ajustes → Envío: zona (países y prefijos de código postal), método y tarifas.
 *
 * @property int $id
 * @property string $nombre
 * @property ?int $transportista_id
 * @property-read ?Transportista $transportista
 * @property ?list<string> $paises
 * @property ?list<string> $prefijos_cp
 * @property MetodoEnvio $metodo
 * @property ?int $precio_kg_extra
 * @property ?int $precio_caja
 * @property ?int $gratis_desde
 * @property int $plazo_dias
 * @property int $prioridad
 * @property bool $activa
 * @property-read Collection<int, TarifaEnvio> $tarifas
 */
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

    /**
     * Zona que atiende un destino: país y, si la zona los tiene, prefijo del código postal.
     * La más concreta primero (con prefijos) y luego la de más prioridad. Es la regla del motor
     * de precios; también la usan las direcciones del cliente (revisión N3 del paso 5.3, M4).
     */
    public static function paraDestino(string $pais, string $codigoPostal): ?self
    {
        $pais = strtoupper($pais);
        $cp = (string) preg_replace('/\s+/', '', $codigoPostal);

        return static::where('activa', true)->get()
            ->filter(fn (self $z) => in_array($pais, array_map('strtoupper', $z->paises ?? []), true)
                && ($z->prefijos_cp === null || $z->prefijos_cp === [] || collect($z->prefijos_cp)->contains(fn ($p) => str_starts_with($cp, (string) $p))))
            ->sortBy([fn ($a, $b) => empty($a->prefijos_cp) <=> empty($b->prefijos_cp), fn ($a, $b) => $b->prioridad <=> $a->prioridad])
            ->first();
    }

    /** @return BelongsTo<Transportista, $this> */
    public function transportista(): BelongsTo
    {
        return $this->belongsTo(Transportista::class);
    }

    /** @return HasMany<TarifaEnvio, $this> */
    public function tarifas(): HasMany
    {
        return $this->hasMany(TarifaEnvio::class)->orderBy('hasta_gramos');
    }
}
