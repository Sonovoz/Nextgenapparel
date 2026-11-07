<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/** Camisetas, polos, sudaderas… (categoría de WooCommerce). */
class Familia extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'familias';

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['nombre', 'slug', 'descripcion'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function prendas(): HasMany
    {
        return $this->hasMany(Prenda::class);
    }
}
