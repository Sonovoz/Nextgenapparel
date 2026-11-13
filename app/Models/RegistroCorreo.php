<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D235: un correo de la tienda y lo que le pasó.
 *
 * @property int $id
 * @property ?string $notificacion_id
 * @property string $tipo
 * @property string $destinatario
 * @property ?string $asunto
 * @property ?int $pedido_id
 * @property string $estado
 * @property int $intentos
 * @property ?string $error
 * @property ?CarbonInterface $enviado_en
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class RegistroCorreo extends Model
{
    use MassPrunable;

    public const DIAS = 90;

    public const ESTADOS = [
        'en_cola' => 'En cola', 'enviando' => 'Enviando', 'enviado' => 'Enviado',
        'reintentando' => 'Reintentando', 'fallido' => 'Fallido', 'no_enviado' => 'No enviado',
    ];

    protected $table = 'registro_correos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enviado_en' => 'datetime', 'intentos' => 'integer'];
    }

    /** @return BelongsTo<Pedido, $this> */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::DIAS));
    }
}
