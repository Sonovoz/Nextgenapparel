<?php

namespace App\Models;

use App\Dominio\Catalogo\DatoCatalogoInvalido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rectángulo de una zona de impresión sobre una foto de maqueta, en
 * centésimas de porcentaje (0–10000) para que no dependa del tamaño de la foto.
 */
class FotoZona extends Model
{
    public const ESCALA = 10000;

    protected $table = 'foto_zona';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['x' => 'integer', 'y' => 'integer', 'ancho' => 'integer', 'alto' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (FotoZona $zona): void {
            if ($zona->ancho <= 0 || $zona->alto <= 0
                || $zona->x < 0 || $zona->y < 0
                || $zona->x + $zona->ancho > self::ESCALA
                || $zona->y + $zona->alto > self::ESCALA) {
                throw new DatoCatalogoInvalido('El rectángulo de la zona debe quedar dentro de la foto.');
            }
        });
    }

    public function foto(): BelongsTo
    {
        return $this->belongsTo(Foto::class);
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaImpresion::class, 'zona_impresion_id');
    }
}
