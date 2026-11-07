<?php

namespace App\Models;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\DatoCatalogoInvalido;
use App\Dominio\Catalogo\Origen;
use App\Dominio\Catalogo\TipoFoto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;

class Foto extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'fotos';

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['texto_alternativo'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoFoto::class,
            'cara' => Cara::class,
            'origen' => Origen::class,
        ];
    }

    public const DISCO = 'public';

    protected static function booted(): void
    {
        // Al borrar la foto se borra también su archivo.
        static::deleted(fn (Foto $foto) => Storage::disk(self::DISCO)->delete($foto->ruta));

        // Una maqueta es siempre de un color y una cara concretos.
        static::saving(function (Foto $foto): void {
            if ($foto->tipo === TipoFoto::Maqueta && ($foto->cara === null || $foto->color_id === null)) {
                throw new DatoCatalogoInvalido('Una foto de maqueta necesita color y cara.');
            }
        });
    }

    public function url(): string
    {
        return Storage::disk(self::DISCO)->url($this->ruta);
    }

    public function prenda(): BelongsTo
    {
        return $this->belongsTo(Prenda::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /** Rectángulos de las zonas de impresión sobre esta foto. */
    public function zonas(): HasMany
    {
        return $this->hasMany(FotoZona::class);
    }
}
