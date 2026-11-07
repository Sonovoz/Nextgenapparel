<?php

namespace App\Models;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Dominio\Sistema\Errores\EstadoError;
use App\Dominio\Sistema\Errores\NivelError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Error agrupado del log de errores: una fila por huella, con su contador.
 */
class RegistroError extends Model
{
    use Prunable;

    protected $table = 'registro_errores';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nivel' => NivelError::class,
            'estado' => EstadoError::class,
            'traza' => 'array',
            'primera_vez' => 'datetime',
            'ultima_vez' => 'datetime',
            'alertado_en' => 'datetime',
            'resuelto_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function resueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }

    public function marcarResuelto(User $usuario): void
    {
        $this->update([
            'estado' => EstadoError::Resuelto,
            'resuelto_en' => now(),
            'resuelto_por' => $usuario->id,
        ]);

        Actividad::apuntar('errores', 'Error marcado como resuelto', $this, quien: $usuario);
    }

    public function reabrir(): void
    {
        $this->update([
            'estado' => EstadoError::Pendiente,
            'resuelto_en' => null,
            'resuelto_por' => null,
        ]);

        Actividad::apuntar('errores', 'Error reabierto', $this);
    }

    /** Los errores resueltos hace tiempo se eliminan (tarea programada, paso 1.5). */
    public function prunable(): Builder
    {
        return static::where('estado', EstadoError::Resuelto)
            ->where('resuelto_en', '<', now()->subDays(config('errores.conservar_resueltos_dias')));
    }
}
