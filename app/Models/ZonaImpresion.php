<?php

namespace App\Models;

use App\Dominio\Catalogo\Cara;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

/** Frontal, pecho, espalda grande… con su medida máxima de impresión. */
class ZonaImpresion extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'zonas_impresion';

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['nombre'];

    protected function casts(): array
    {
        return ['cara' => Cara::class, 'activa' => 'boolean'];
    }

    public function prendas(): BelongsToMany
    {
        return $this->belongsToMany(Prenda::class, 'prenda_zona')
            ->using(PrendaZona::class)
            ->withPivot(['id', 'precio', 'coste'])
            ->withTimestamps();
    }
}
