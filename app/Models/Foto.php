<?php

namespace App\Models;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\DatoCatalogoInvalido;
use App\Dominio\Catalogo\Origen;
use App\Dominio\Catalogo\TipoFoto;
use App\Models\Concerns\Traducible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Foto extends Model
{
    use HasFactory, Traducible;

    protected $table = 'fotos';

    protected $guarded = ['id'];

    /** Al cambiar, se actualiza la fecha de la prenda (lastmod del sitemap). */
    protected $touches = ['prenda'];

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
        // Tras confirmar la transacción: si se deshace, el archivo sigue ahí.
        static::deleted(fn (Foto $foto) => DB::afterCommit(fn () => Storage::disk(self::DISCO)->delete($foto->ruta)));

        // Al cambiar la imagen, el archivo anterior no se queda huérfano en el disco.
        static::updated(function (Foto $foto): void {
            if ($foto->wasChanged('ruta') && filled($foto->getOriginal('ruta'))) {
                $anterior = $foto->getOriginal('ruta');
                DB::afterCommit(fn () => Storage::disk(self::DISCO)->delete($anterior));
            }
        });

        // Una maqueta es siempre de un color y una cara concretos. Si deja de
        // ser maqueta, pierde la cara y las zonas marcadas.
        static::saving(function (Foto $foto): void {
            if ($foto->tipo === TipoFoto::Maqueta && ($foto->cara === null || $foto->color_id === null)) {
                throw new DatoCatalogoInvalido('Una foto de maqueta necesita color y cara.');
            }
            if ($foto->tipo !== TipoFoto::Maqueta) {
                $foto->cara = null;
            }
        });
        static::saved(function (Foto $foto): void {
            if ($foto->tipo !== TipoFoto::Maqueta) {
                $foto->zonas()->delete();
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
