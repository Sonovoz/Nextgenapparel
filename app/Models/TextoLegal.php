<?php

namespace App\Models;

use App\Dominio\Legal\PaginaLegal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Versión de un texto legal (paso 8.1, D183), portado de Clave de Alma (ADR 0020). Inmutable: un
 * cambio crea una versión nueva; solo se marca su revisión por la asesoría.
 *
 * @property int $id
 * @property PaginaLegal $pagina
 * @property string $idioma
 * @property int $version
 * @property string $contenido
 * @property ?list<string> $pendientes
 * @property Carbon $generado_en
 * @property ?Carbon $revisado_en
 * @property ?string $revisado_por
 */
class TextoLegal extends Model
{
    private const CAMBIABLES = ['revisado_en', 'revisado_por', 'updated_at'];

    protected $table = 'textos_legales';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['pagina' => PaginaLegal::class, 'pendientes' => 'array', 'generado_en' => 'datetime', 'revisado_en' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (TextoLegal $texto): void {
            if (array_diff(array_keys($texto->getDirty()), self::CAMBIABLES) !== []) {
                throw new LogicException('Un texto legal no se modifica: se genera una versión nueva.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('Los textos legales no se borran: quedan como historial.'));
    }

    public static function actual(PaginaLegal $pagina, string $idioma = 'es'): ?self
    {
        return self::where('pagina', $pagina->value)->where('idioma', $idioma)->latest('version')->first();
    }

    /** Lo que se guarda en el pedido: qué condiciones aceptó el cliente. */
    public function etiqueta(): string
    {
        return $this->pagina->value.($this->idioma === 'es' ? '' : '-'.$this->idioma).' v'.$this->version;
    }
}
