<?php

namespace App\Models;

use App\Dominio\Precios\Efecto;
use Illuminate\Database\Eloquent\Model;

/** Marketing → Cupones. */
class Cupon extends Model
{
    protected $table = 'cupones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => Efecto::class, 'valor' => 'integer', 'minimo_importe' => 'integer', 'usos_maximos' => 'integer', 'usos' => 'integer',
            'un_uso_por_cliente' => 'boolean', 'activo' => 'boolean', 'desde' => 'datetime', 'hasta' => 'datetime',
        ];
    }

    /** Los códigos se guardan en mayúsculas y sin espacios: «verano10» = «VERANO10». */
    public static function normalizar(string $codigo): string
    {
        return mb_strtoupper(trim($codigo));
    }

    protected static function booted(): void
    {
        static::saving(fn (Cupon $cupon) => $cupon->codigo = self::normalizar((string) $cupon->codigo));
    }
}
