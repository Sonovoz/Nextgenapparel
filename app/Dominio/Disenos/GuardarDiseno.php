<?php

namespace App\Dominio\Disenos;

use App\Dominio\Sistema\Salud\Comprobaciones\EspacioDisco;
use App\Models\Ajuste;
use App\Models\Diseno;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use Throwable;

/**
 * Guarda un diseño subido (fase 4): lo analiza, guarda el original sin tocar
 * en el disco privado y una miniatura WebP de lo que se imprime para mostrarla.
 * Si el mismo dueño sube el mismo archivo otra vez, se reutiliza el que había.
 *
 * Frenos (revisión N3): como mucho ANALISIS_SIMULTANEOS análisis a la vez en el
 * servidor, un tope de diseños nuevos por visitante y día (Ajustes) y nada de
 * subidas con el disco casi lleno.
 */
final class GuardarDiseno
{
    public const DISCO = 'disenos';

    public const ANALISIS_SIMULTANEOS = 2;

    private const MINIATURA_PX = 600;

    /** Segundos que se espera un turno libre antes de pedir al cliente que lo intente de nuevo. */
    public static int $esperaSegundos = 15;

    public function __construct(private readonly AnalizadorPng $analizador) {}

    public function __invoke(UploadedFile $archivo, string $propietario): Diseno
    {
        $origen = (string) $archivo->getRealPath();
        $sha256 = (string) hash_file('sha256', $origen);

        $existente = $this->existente($propietario, $sha256);
        if ($existente !== null) {
            return $existente;
        }

        $this->comprobarCapacidad($propietario);

        $miniatura = null;
        // Cierre normal, no «fn»: la miniatura tiene que salir por referencia.
        $analisis = $this->conTurno(function () use ($origen, &$miniatura): AnalisisPng {
            return $this->analizador->analizar($origen, conImagen: function (Imagick $imagen, AnalisisPng $a) use (&$miniatura): void {
                $miniatura = $this->miniatura($imagen, $a);
            });
        });

        if (! is_string($miniatura) || $miniatura === '') {
            throw new DisenoNoValido('procesar');
        }

        $uuid = (string) Str::uuid();
        $carpeta = now()->format('Y/m');
        $rutas = ['original' => "{$carpeta}/{$uuid}.png", 'miniatura' => "{$carpeta}/{$uuid}-mini.webp"];
        $disco = Storage::disk(self::DISCO);

        try {
            $disco->putFileAs($carpeta, $archivo, "{$uuid}.png");
            $disco->put($rutas['miniatura'], $miniatura);

            return Diseno::create([
                'uuid' => $uuid,
                'propietario' => $propietario,
                'nombre_original' => self::nombre($archivo->getClientOriginalName()),
                'ruta' => $rutas['original'],
                'miniatura' => $rutas['miniatura'],
                'bytes' => (int) $archivo->getSize(),
                'sha256' => $sha256,
                ...$analisis->aColumnas(),
                'derechos_declarados_en' => now(),
                'derechos_texto' => __('disenos.derechos_casilla'),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Otra petición igual (doble clic, reintento) llegó antes: se usa la suya.
            $disco->delete(array_values($rutas));

            return $this->existente($propietario, $sha256) ?? throw new DisenoNoValido('no_disponible');
        } catch (Throwable $error) {
            // Nada a medias: sin fila no quedan archivos huérfanos.
            $disco->delete(array_values($rutas));

            throw $error;
        }
    }

    /** Nombre que puso el cliente, sin rutas ni caracteres de control o de formato, para mostrarlo. */
    public static function nombre(string $original): string
    {
        $nombre = basename(str_replace('\\', '/', $original));
        $nombre = trim((string) preg_replace('/[\p{Cc}\p{Cf}]/u', '', $nombre));

        return mb_substr($nombre, 0, 150) ?: 'diseno.png';
    }

    private function existente(string $propietario, string $sha256): ?Diseno
    {
        return Diseno::where('propietario', $propietario)->where('sha256', $sha256)->first();
    }

    private function comprobarCapacidad(string $propietario): void
    {
        $libre = @disk_free_space(Storage::disk(self::DISCO)->path(''));
        $total = @disk_total_space(Storage::disk(self::DISCO)->path(''));
        if ($libre !== false && $total !== false && $total > 0 && $libre / $total * 100 < EspacioDisco::FALLO_PORCENTAJE_LIBRE) {
            throw new DisenoNoValido('no_disponible');
        }

        $hoy = Diseno::where('propietario', $propietario)->where('created_at', '>=', now()->startOfDay())->count();
        if ($hoy >= (int) Ajuste::obtener('disenos.max_dia')) {
            throw new DisenoNoValido('limite_dia');
        }
    }

    /**
     * Ejecuta el análisis con uno de los ANALISIS_SIMULTANEOS turnos libres: así unos pocos
     * PNG grandes a la vez no se reparten la memoria y el disco del servidor.
     *
     * @template T
     *
     * @param  callable(): T  $trabajo
     * @return T
     */
    private function conTurno(callable $trabajo): mixed
    {
        $limite = microtime(true) + self::$esperaSegundos;
        do {
            foreach (range(1, self::ANALISIS_SIMULTANEOS) as $turno) {
                $candado = Cache::lock("disenos:analisis:{$turno}", 120);
                if ($candado->get()) {
                    return $this->liberando($candado, $trabajo);
                }
            }
            if (microtime(true) < $limite) {
                usleep(250_000);
            }
        } while (microtime(true) < $limite);

        throw new DisenoNoValido('ocupado');
    }

    private function liberando(Lock $candado, callable $trabajo): mixed
    {
        try {
            return $trabajo();
        } finally {
            $candado->release();
        }
    }

    /** WebP de lo que se imprime (sin márgenes transparentes), con su transparencia, a partir de la imagen ya leída. */
    private function miniatura(Imagick $imagen, AnalisisPng $analisis): string
    {
        $imagen->cropImage($analisis->utilAnchoPx, $analisis->utilAltoPx, $analisis->utilX, $analisis->utilY);
        $imagen->setImagePage(0, 0, 0, 0);
        if (max($analisis->utilAnchoPx, $analisis->utilAltoPx) > self::MINIATURA_PX) {
            $imagen->thumbnailImage(self::MINIATURA_PX, self::MINIATURA_PX, true);
        }
        $imagen->stripImage();
        $imagen->setImageFormat('webp');
        $imagen->setImageCompressionQuality(82);

        return $imagen->getImagesBlob();
    }
}
