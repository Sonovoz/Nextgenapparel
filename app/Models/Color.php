<?php

namespace App\Models;

use App\Dominio\Catalogo\DatoCatalogoInvalido;
use App\Models\Concerns\Traducible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    use HasFactory, Traducible;

    protected $table = 'colores';

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['nombre'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Color $color): void {
            $color->codigo_hex = strtoupper((string) $color->codigo_hex);

            if (! preg_match('/^#[0-9A-F]{6}$/', $color->codigo_hex)) {
                throw new DatoCatalogoInvalido('El código de color debe tener el formato #RRGGBB.');
            }
        });
    }
}
