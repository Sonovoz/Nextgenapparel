<?php

namespace App\Dominio\Sistema\Servidor;

use App\Models\Ajuste;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Imagick;

/**
 * Herramientas → Servidor (D207): lo que necesita el portal en el servidor de OVH (PHP 8.3, MariaDB y
 * Redis compartidos detrás de Nginx Proxy Manager) y si lo tiene. Solo lectura y nunca una contraseña.
 */
final class InformeServidor
{
    /** Extensiones de PHP que usa el portal y para qué. */
    public const EXTENSIONES = [
        'pdo_mysql' => 'base de datos MariaDB/MySQL',
        'mbstring' => 'textos con acentos',
        'intl' => 'idiomas y formatos',
        'bcmath' => 'cálculos exactos',
        'gd' => 'PDF con imágenes',
        'imagick' => 'analizar diseños y maquetas',
        'zip' => 'paquetes de producción y copias',
        'exif' => 'fotos',
        'fileinfo' => 'tipo de los archivos subidos',
        'openssl' => 'Redsys y cifrado',
        'curl' => 'servicios externos',
    ];

    /** @return list<array{grupo: string, nombre: string, valor: string, estado: 'correcto'|'aviso'|'fallo'}> */
    public static function filas(): array
    {
        $f = [];
        $add = function (string $grupo, string $nombre, string $valor, string $estado) use (&$f): void {
            $f[] = ['grupo' => $grupo, 'nombre' => $nombre, 'valor' => $valor, 'estado' => $estado];
        };

        // PHP
        $add('PHP', 'Versión', PHP_VERSION.' ('.PHP_SAPI.')', version_compare(PHP_VERSION, '8.3.0', '>=') ? 'correcto' : 'fallo');
        $memoria = self::bytes((string) ini_get('memory_limit'));
        $add('PHP', 'Memoria (memory_limit)', (string) ini_get('memory_limit'), $memoria < 0 || $memoria >= 256 * 1024 * 1024 ? 'correcto' : 'aviso');
        $subida = min(self::bytes((string) ini_get('upload_max_filesize')), self::bytes((string) ini_get('post_max_size')));
        $maxMb = (int) rescue(fn () => Ajuste::obtener('disenos.max_mb'), 20, report: false);
        $add('PHP', 'Subida máxima (upload_max_filesize / post_max_size)', ini_get('upload_max_filesize').' / '.ini_get('post_max_size'), $subida >= $maxMb * 1024 * 1024 ? 'correcto' : 'aviso');
        foreach (self::EXTENSIONES as $ext => $para) {
            $add('Extensiones', $ext, extension_loaded($ext) ? 'Sí · '.$para : 'Falta · '.$para, extension_loaded($ext) ? 'correcto' : 'fallo');
        }
        if (extension_loaded('imagick')) {
            $add('Extensiones', 'Imagick con WebP y PNG', Imagick::queryFormats('WEBP') && Imagick::queryFormats('PNG') ? 'Sí' : 'Falta WebP o PNG', Imagick::queryFormats('WEBP') && Imagick::queryFormats('PNG') ? 'correcto' : 'fallo');
        }

        // Base de datos (sin usuario ni contraseña)
        $conexion = (string) config('database.default');
        $cfg = (array) config("database.connections.{$conexion}");
        $version = rescue(fn () => match ($cfg['driver'] ?? '') {
            'sqlite' => 'SQLite '.DB::selectOne('select sqlite_version() as v')->v,
            default => (string) DB::selectOne('select version() as v')->v,
        }, null, report: false);
        $add('Base de datos', 'Tipo y versión', $version ?? 'No responde', $version === null ? 'fallo' : 'correcto');
        if (($cfg['driver'] ?? '') !== 'sqlite') {
            $add('Base de datos', 'Servidor', ($cfg['host'] ?? '?').':'.($cfg['port'] ?? '?'), 'correcto');
            $add('Base de datos', 'Base', (string) ($cfg['database'] ?? '?'), 'correcto');
        }

        // Caché, colas y sesiones (Redis compartido: cada entorno con su prefijo)
        $usaRedis = in_array('redis', [config('cache.default'), config('queue.default'), config('session.driver')], true);
        $add('Caché, colas y sesiones', 'Drivers', 'caché '.config('cache.default').' · colas '.config('queue.default').' · sesiones '.config('session.driver'), 'correcto');
        if ($usaRedis) {
            $cliente = (string) config('database.redis.client');
            $add('Caché, colas y sesiones', 'Cliente de Redis', $cliente, $cliente === 'phpredis' && ! extension_loaded('redis') ? 'fallo' : 'correcto');
            $ping = rescue(fn () => (bool) Redis::connection()->ping(), false, report: false);
            $add('Caché, colas y sesiones', 'Redis responde', $ping ? 'Sí ('.config('database.redis.default.host').')' : 'No', $ping ? 'correcto' : 'fallo');
            $prefijo = (string) config('database.redis.options.prefix');
            $add('Caché, colas y sesiones', 'Prefijo propio en Redis', $prefijo, $prefijo !== '' && ! str_starts_with($prefijo, 'laravel') ? 'correcto' : 'aviso');
        }

        // Aplicación
        $produccion = app()->environment('production');
        $add('Aplicación', 'Entorno', (string) app()->environment(), 'correcto');
        $add('Aplicación', 'Depuración (APP_DEBUG)', config('app.debug') ? 'Activada' : 'Desactivada', config('app.debug') && $produccion ? 'fallo' : 'correcto');
        $url = (string) config('app.url');
        $add('Aplicación', 'Dirección (APP_URL)', $url, $produccion && ! str_starts_with($url, 'https://') ? 'fallo' : 'correcto');
        $add('Aplicación', 'Zona horaria', (string) config('app.timezone'), 'correcto');
        $add('Aplicación', 'Carpeta storage con escritura', is_writable(storage_path()) ? 'Sí' : 'No', is_writable(storage_path()) ? 'correcto' : 'fallo');
        $add('Aplicación', 'Enlace public/storage', is_link(public_path('storage')) || is_dir(public_path('storage')) ? 'Sí' : 'Falta: php artisan storage:link', is_link(public_path('storage')) || is_dir(public_path('storage')) ? 'correcto' : 'aviso');

        return $f;
    }

    /** ¿Todo lo imprescindible está? (sin ningún fallo) */
    public static function listo(): bool
    {
        return collect(self::filas())->every(fn (array $fila) => $fila['estado'] !== 'fallo');
    }

    /** «256M» → bytes; -1 (sin límite) se devuelve tal cual. */
    private static function bytes(string $valor): int
    {
        $valor = trim($valor);
        if ($valor === '' || $valor === '-1') {
            return -1;
        }
        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }
}
