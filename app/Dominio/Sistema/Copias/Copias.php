<?php

namespace App\Dominio\Sistema\Copias;

use App\Dominio\Sistema\Errores\ErrorDeCopia;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDO;
use Throwable;
use ZipArchive;

/**
 * Copias de seguridad: hacerlas, listarlas y probar que se pueden restaurar.
 *
 * El paquete spatie/laravel-backup comprime, cifra, reparte entre discos y
 * borra las antiguas. La base de datos SQLite la copia esta clase con
 * VACUUM INTO, porque el paquete necesitaría el programa sqlite3.
 */
class Copias
{
    public const DISCO = 'copias';

    public const ARCHIVO_SQLITE = 'base-de-datos.sqlite';

    /** Hace una copia completa. Devuelve null si va bien o el mensaje de error. */
    public function hacer(): ?string
    {
        $temporal = storage_path('copias-temp');
        File::ensureDirectoryExists($temporal);
        File::cleanDirectory($temporal);
        File::put($temporal.'/.gitignore', "*\n!.gitignore\n");

        try {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $destino = $temporal.DIRECTORY_SEPARATOR.self::ARCHIVO_SQLITE;
                DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($destino));
            }

            // Si falla, el paquete lanza el evento BackupHasFailed, que se
            // registra en el log de errores y avisa (AppServiceProvider).
            $codigo = Artisan::call('backup:run', ['--disable-notifications' => true]);

            return $codigo === 0 ? null : (trim(Artisan::output()) ?: 'La copia no se ha completado.');
        } catch (Throwable $error) {
            report(new ErrorDeCopia('No se pudo hacer la copia de seguridad: '.$error->getMessage(), previous: $error));

            return $error->getMessage();
        } finally {
            File::cleanDirectory($temporal);
            File::put($temporal.'/.gitignore', "*\n!.gitignore\n");
        }
    }

    /**
     * Copias guardadas, de la más reciente a la más antigua.
     *
     * @return list<array{disco: string, ruta: string, nombre: string, fecha: CarbonImmutable, bytes: int}>
     */
    public static function listar(): array
    {
        $copias = [];

        foreach (config('backup.backup.destination.disks') as $disco) {
            try {
                foreach (Storage::disk($disco)->allFiles(config('backup.backup.name')) as $ruta) {
                    if (! str_ends_with($ruta, '.zip')) {
                        continue;
                    }

                    $copias[] = [
                        'disco' => $disco,
                        'ruta' => $ruta,
                        'nombre' => basename($ruta),
                        'fecha' => CarbonImmutable::createFromTimestamp(Storage::disk($disco)->lastModified($ruta), config('app.timezone')),
                        'bytes' => Storage::disk($disco)->size($ruta),
                    ];
                }
            } catch (Throwable) {
                // Un disco que no responde (p. ej. el externo) no impide ver los demás.
            }
        }

        usort($copias, fn ($a, $b) => $b['fecha'] <=> $a['fecha']);

        return $copias;
    }

    /**
     * Abre la copia más reciente del disco local, la descomprime en una
     * carpeta temporal y comprueba que la base de datos se puede leer.
     *
     * @return array{correcto: bool, mensaje: string}
     */
    public function probarRestauracion(): array
    {
        $copia = collect(self::listar())->firstWhere('disco', self::DISCO);

        if ($copia === null) {
            return $this->guardarPrueba(false, 'No hay ninguna copia que probar.');
        }

        $carpeta = storage_path('copias-temp/prueba-'.uniqid());
        File::ensureDirectoryExists($carpeta);

        try {
            $zip = new ZipArchive;

            if ($zip->open(Storage::disk(self::DISCO)->path($copia['ruta'])) !== true) {
                return $this->guardarPrueba(false, 'El archivo de la copia está dañado y no se puede abrir.');
            }

            if (filled($clave = config('backup.backup.password'))) {
                $zip->setPassword($clave);
            }

            if (! $zip->extractTo($carpeta)) {
                $zip->close();

                return $this->guardarPrueba(false, 'No se puede descomprimir la copia (¿contraseña incorrecta?).');
            }

            $archivos = $zip->numFiles;
            $zip->close();

            $tablas = $this->contarTablas($carpeta);

            if ($tablas === null) {
                return $this->guardarPrueba(false, 'La copia no contiene la base de datos.');
            }

            return $this->guardarPrueba(true, "Copia del {$copia['fecha']->format('d/m/Y H:i')} restaurable: {$archivos} archivos y {$tablas} tablas leídas.");
        } catch (Throwable $error) {
            return $this->guardarPrueba(false, 'Error al probar la copia: '.$error->getMessage());
        } finally {
            File::deleteDirectory($carpeta);
        }
    }

    /** Tablas de la base de datos de la copia, o null si no está. */
    private function contarTablas(string $carpeta): ?int
    {
        $sqlite = collect(File::allFiles($carpeta))->first(fn ($f) => $f->getFilename() === self::ARCHIVO_SQLITE);

        if ($sqlite !== null) {
            $pdo = new PDO('sqlite:'.$sqlite->getPathname());
            $tablas = (int) $pdo->query("select count(*) from sqlite_master where type = 'table'")->fetchColumn();
            $pdo->query('select count(*) from users')->fetchColumn();
            $pdo = null;

            return $tablas;
        }

        $volcado = collect(File::allFiles($carpeta))->first(fn ($f) => str_ends_with($f->getFilename(), '.sql'));

        if ($volcado === null) {
            return null;
        }

        $tablas = preg_match_all('/^CREATE TABLE/m', (string) file_get_contents($volcado->getPathname()));

        return $tablas > 0 ? $tablas : null;
    }

    /** @return array{correcto: bool, mensaje: string} */
    private function guardarPrueba(bool $correcto, string $mensaje): array
    {
        $resultado = ['correcto' => $correcto, 'mensaje' => $mensaje, 'fecha' => now()->getTimestamp()];
        file_put_contents(self::archivoPrueba(), (string) json_encode($resultado, JSON_UNESCAPED_UNICODE), LOCK_EX);

        if (! $correcto) {
            report(new ErrorDeCopia('Prueba de restauración fallida: '.$mensaje));
        }

        return ['correcto' => $correcto, 'mensaje' => $mensaje];
    }

    /** @return array{correcto: bool, mensaje: string, fecha: CarbonImmutable}|null */
    public static function ultimaPrueba(): ?array
    {
        $datos = json_decode((string) @file_get_contents(self::archivoPrueba()), true);

        if (! is_array($datos)) {
            return null;
        }

        $datos['fecha'] = CarbonImmutable::createFromTimestamp($datos['fecha'], config('app.timezone'));

        return $datos;
    }

    public static function archivoPrueba(): string
    {
        return storage_path('framework/prueba-restauracion.json');
    }
}
