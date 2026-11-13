<?php

namespace App\Dominio\Sistema\Almacenamiento;

use App\Dominio\Sistema\Copias\Copias;
use App\Models\Ajuste;
use App\Models\Diseno;
use App\Models\Pedido;
use FilesystemIterator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * D231: cuánto ocupa el proyecto, por tipo, frente al espacio máximo elegido en Herramientas → Almacenamiento.
 * Medir carpetas puede tardar: se guarda 10 minutos (se puede recalcular a mano).
 */
final class Almacenamiento
{
    public const CACHE = 'almacenamiento.medida';

    /** Espacios máximos que se pueden elegir, en GB. */
    public const OPCIONES_GB = [5, 10, 20, 50, 100, 200, 500];

    /** @return list<array{clave: string, nombre: string, bytes: int, nota: string}> */
    public static function medir(bool $recalcular = false): array
    {
        if ($recalcular) {
            Cache::forget(self::CACHE);
        }

        return Cache::remember(self::CACHE, now()->addMinutes(10), fn () => [
            ['clave' => 'disenos_sin_venta', 'nombre' => 'Diseños sin venta', 'bytes' => self::bytesDisenos(vendidos: false),
                'nota' => 'Se borran solos a las '.(int) Ajuste::obtener('disenos.horas_sin_venta').' h.'],
            ['clave' => 'disenos_vendidos', 'nombre' => 'Diseños vendidos (biblioteca)', 'bytes' => self::bytesDisenos(vendidos: true),
                'nota' => 'Mientras sus pedidos guardan los archivos.'],
            ['clave' => 'pedidos', 'nombre' => 'Archivos de los pedidos', 'bytes' => self::carpeta(self::raiz(Pedido::DISCO)),
                'nota' => 'Originales, maquetas y fotos; los originales, '.(int) Ajuste::obtener('pedidos.dias_archivos').' días hábiles.'],
            ['clave' => 'catalogo', 'nombre' => 'Fotos del catálogo', 'bytes' => self::carpeta(self::raiz('public')),
                'nota' => 'Optimizadas a WebP al subirlas.'],
            ['clave' => 'copias', 'nombre' => 'Copias de seguridad', 'bytes' => self::carpeta(self::raiz(Copias::DISCO)),
                'nota' => '7 diarias, 4 semanales y 12 mensuales.'],
            ['clave' => 'registros', 'nombre' => 'Registros (logs)', 'bytes' => self::carpeta(storage_path('logs')), 'nota' => ''],
            ['clave' => 'base_de_datos', 'nombre' => 'Base de datos', 'bytes' => self::baseDeDatos(), 'nota' => ''],
        ]);
    }

    public static function total(): int
    {
        return array_sum(array_column(self::medir(), 'bytes'));
    }

    public static function limiteBytes(): int
    {
        return max(1, (int) Ajuste::obtener('almacenamiento.limite_gb')) * 1024 ** 3;
    }

    public static function porcentaje(): int
    {
        return (int) floor(self::total() / self::limiteBytes() * 100);
    }

    public static function legible(int|float $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => number_format($bytes / 1024 ** 3, 2, ',', '.').' GB',
            $bytes >= 1024 ** 2 => number_format($bytes / 1024 ** 2, 1, ',', '.').' MB',
            default => number_format($bytes / 1024, 0, ',', '.').' KB',
        };
    }

    private static function bytesDisenos(bool $vendidos): int
    {
        $vendidosIds = DB::table('impresiones_pedido')->whereNotNull('diseno_id')->select('diseno_id');
        $consulta = $vendidos ? Diseno::whereIn('id', $vendidosIds) : Diseno::whereNotIn('id', $vendidosIds);

        return (int) $consulta->sum('bytes');
    }

    private static function raiz(string $disco): ?string
    {
        try {
            return Storage::disk($disco)->path('');
        } catch (Throwable) {
            return null; // un disco que no es local (S3) no se mide aquí
        }
    }

    private static function carpeta(?string $ruta): int
    {
        if ($ruta === null || ! is_dir($ruta)) {
            return 0;
        }
        $bytes = 0;
        try {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS)) as $archivo) {
                if ($archivo->isFile() && ! $archivo->isLink()) {
                    $bytes += $archivo->getSize();
                }
            }
        } catch (Throwable) {
            // una carpeta sin permiso de lectura no impide medir el resto
        }

        return $bytes;
    }

    private static function baseDeDatos(): int
    {
        try {
            $conexion = DB::connection();
            if ($conexion->getDriverName() === 'sqlite') {
                $archivo = (string) $conexion->getConfig('database');

                return is_file($archivo) ? (int) filesize($archivo) : 0;
            }

            return (int) DB::table('information_schema.tables')->where('table_schema', $conexion->getDatabaseName())
                ->sum(DB::raw('data_length + index_length'));
        } catch (Throwable) {
            return 0;
        }
    }
}
