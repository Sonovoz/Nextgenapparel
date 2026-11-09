<?php

namespace App\Models;

use App\Models\Concerns\Traducible;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Entrada del blog (v2, D195): textos es/en en Markdown, SEO propio y fecha de publicación (null: borrador).
 *
 * @property int $id
 * @property ?Carbon $publicada_en
 */
class EntradaBlog extends Model
{
    use Traducible;

    /** @var list<string> */
    public array $translatable = ['titulo', 'slug', 'extracto', 'contenido', 'seo_titulo', 'seo_descripcion'];

    protected $table = 'entradas_blog';

    protected $guarded = ['id'];

    /** @param  Builder<EntradaBlog>  $consulta */
    public function scopePublicadas(Builder $consulta): void
    {
        $consulta->whereNotNull('publicada_en')->where('publicada_en', '<=', now());
    }

    protected function casts(): array
    {
        return ['publicada_en' => 'datetime'];
    }
}
