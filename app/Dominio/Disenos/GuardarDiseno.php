<?php

namespace App\Dominio\Disenos;

use App\Models\Diseno;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use Throwable;

/**
 * Guarda un diseño subido (fase 4): lo analiza, guarda el original sin tocar
 * en el disco privado y una miniatura WebP de lo que se imprime para mostrarla.
 * Si el mismo dueño sube el mismo archivo otra vez, se reutiliza el que había.
 */
final class GuardarDiseno
{
    public const DISCO = 'disenos';

    private const MINIATURA_PX = 600;

    public function __construct(private readonly AnalizadorPng $analizador) {}

    public function __invoke(UploadedFile $archivo, string $propietario): Diseno
    {
        $origen = (string) $archivo->getRealPath();
        $analisis = $this->analizador->analizar($origen);
        $sha256 = (string) hash_file('sha256', $origen);

        $existente = Diseno::where('propietario', $propietario)->where('sha256', $sha256)->first();
        if ($existente !== null) {
            return $existente;
        }

        $uuid = (string) Str::uuid();
        $carpeta = now()->format('Y/m');
        $ruta = "{$carpeta}/{$uuid}.png";
        $miniatura = "{$carpeta}/{$uuid}-mini.webp";
        $disco = Storage::disk(self::DISCO);

        try {
            $disco->putFileAs($carpeta, $archivo, "{$uuid}.png");
            $disco->put($miniatura, $this->miniatura($origen, $analisis));

            return Diseno::create([
                'uuid' => $uuid,
                'propietario' => $propietario,
                'nombre_original' => self::nombre($archivo->getClientOriginalName()),
                'ruta' => $ruta,
                'miniatura' => $miniatura,
                'bytes' => (int) $archivo->getSize(),
                'sha256' => $sha256,
                ...$analisis->aColumnas(),
                'derechos_declarados_en' => now(),
            ]);
        } catch (Throwable $error) {
            // Nada a medias: sin fila no quedan archivos huérfanos.
            $disco->delete([$ruta, $miniatura]);

            throw $error;
        }
    }

    /** Nombre que puso el cliente, sin rutas ni caracteres de control, para mostrarlo. */
    public static function nombre(string $original): string
    {
        $nombre = basename(str_replace('\\', '/', $original));
        $nombre = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $nombre));

        return mb_substr($nombre, 0, 150) ?: 'diseno.png';
    }

    /** WebP de lo que se imprime (sin márgenes transparentes), con su transparencia. */
    private function miniatura(string $origen, AnalisisPng $analisis): string
    {
        $imagen = new Imagick;
        try {
            $imagen->readImage('png:'.$origen.'[0]');
            $imagen->cropImage($analisis->utilAnchoPx, $analisis->utilAltoPx, $analisis->utilX, $analisis->utilY);
            $imagen->setImagePage(0, 0, 0, 0);
            if (max($analisis->utilAnchoPx, $analisis->utilAltoPx) > self::MINIATURA_PX) {
                $imagen->thumbnailImage(self::MINIATURA_PX, self::MINIATURA_PX, true);
            }
            $imagen->stripImage();
            $imagen->setImageFormat('webp');
            $imagen->setImageCompressionQuality(82);

            return $imagen->getImagesBlob();
        } finally {
            $imagen->clear();
        }
    }
}
