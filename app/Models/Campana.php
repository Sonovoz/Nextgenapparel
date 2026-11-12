<?php

namespace App\Models;

use App\Models\Concerns\Traducible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Campaña manual de Marketing (v2, D190): asunto y texto es/en, segmento y cupón opcional. Se envía una vez.
 *
 * @property int $id
 * @property string $nombre
 * @property string $segmento todos | con_pedidos | sin_pedidos
 * @property ?string $cupon
 * @property ?Carbon $enviada_en
 * @property int $enviados
 */
class Campana extends Model
{
    use Traducible;

    /** @var list<string> */
    public array $translatable = ['asunto', 'texto'];

    protected $table = 'campanas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enviada_en' => 'datetime', 'enviados' => 'integer'];
    }
}
