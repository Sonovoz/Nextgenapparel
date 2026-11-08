<?php

namespace App\Models;

use App\Models\Concerns\Traducible;
use Illuminate\Database\Eloquent\Model;

/** Ajustes → Impuestos: clase de IVA (y su recargo de equivalencia), en centésimas de %. */
class ClaseImpuesto extends Model
{
    use Traducible;

    protected $table = 'clases_impuesto';

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['nombre'];

    protected function casts(): array
    {
        return ['tipo_iva' => 'integer', 'recargo' => 'integer', 'por_defecto' => 'boolean'];
    }

    public static function porDefecto(): ?self
    {
        return static::where('por_defecto', true)->orderBy('id')->first();
    }
}
