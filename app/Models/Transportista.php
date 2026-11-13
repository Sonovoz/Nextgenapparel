<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ajustes → Transportistas (D226): ficha de una empresa de transporte. Sus tarifas son las de las
 * zonas de envío que lleva; la URL de seguimiento lleva {numero} y se rellena al enviar el pedido.
 *
 * @property int $id
 * @property string $nombre
 * @property ?string $web
 * @property ?string $url_seguimiento
 * @property ?string $contacto
 * @property ?string $telefono
 * @property ?string $correo
 * @property ?string $numero_cliente
 * @property ?string $notas
 * @property bool $activo
 * @property-read Collection<int, ZonaEnvio> $zonas
 */
class Transportista extends Model
{
    protected $table = 'transportistas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** @return HasMany<ZonaEnvio, $this> */
    public function zonas(): HasMany
    {
        return $this->hasMany(ZonaEnvio::class)->orderByDesc('prioridad');
    }

    /** Enlace de seguimiento de un envío, o null si el transportista no tiene URL o el número está vacío. */
    public function enlace(string $numero): ?string
    {
        $numero = trim($numero);
        if ($numero === '' || blank($this->url_seguimiento) || ! str_contains((string) $this->url_seguimiento, '{numero}')) {
            return null;
        }

        return str_replace('{numero}', rawurlencode($numero), (string) $this->url_seguimiento);
    }
}
