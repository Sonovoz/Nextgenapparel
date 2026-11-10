<?php

namespace App\Http\Controllers;

use App\Dominio\Disenos\AnalizadorPng;
use App\Dominio\Disenos\DisenoNoValido;
use App\Dominio\Disenos\Dueno;
use App\Dominio\Disenos\GuardarDiseno;
use App\Models\Diseno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Subida de diseños desde la tienda (fase 4). */
class DisenoController
{
    public function subir(Request $request, GuardarDiseno $guardar, string $idioma): JsonResponse
    {
        $maxMb = AnalizadorPng::maxMbEfectivo();

        $request->validate([
            'archivo' => ['required', 'file', 'max:'.($maxMb * 1024)],
        ], [
            'archivo.required' => __('disenos.error_sin_archivo'),
            'archivo.file' => __('disenos.error_subida', ['mb' => $maxMb]),
            'archivo.uploaded' => __('disenos.error_subida', ['mb' => $maxMb]),
            'archivo.max' => __('disenos.error_pesado', ['mb' => $maxMb]),
        ]);

        try {
            $diseno = $guardar($request->file('archivo'), Dueno::de($request));
        } catch (DisenoNoValido $error) {
            return response()->json(['message' => $error->mensaje(), 'motivo' => $error->motivo], 422);
        }

        return response()->json(self::datos($diseno), $diseno->wasRecentlyCreated ? 201 : 200);
    }

    /** Biblioteca del visitante: sus diseños, del más reciente al más antiguo. */
    public function lista(Request $request, string $idioma): JsonResponse
    {
        $dueno = Dueno::de($request, crear: false);
        $disenos = $dueno === null ? collect() : $dueno->filtrar(Diseno::query())->latest('id')->limit(60)->get();

        return response()->json(['disenos' => $disenos->map(fn (Diseno $d) => self::datos($d))->values()]);
    }

    /** El visitante borra un diseño suyo (y sus archivos). A los demás, 404. */
    public function borrar(Request $request, string $idioma, string $diseno): JsonResponse
    {
        $this->delVisitante($request, $diseno)->delete();

        return response()->json(['borrado' => true]);
    }

    private function delVisitante(Request $request, string $uuid): Diseno
    {
        $dueno = Dueno::de($request, crear: false);
        abort_if($dueno === null, 404);

        return $dueno->filtrar(Diseno::query())->where('uuid', $uuid)->firstOrFail();
    }

    /** Miniatura de un diseño, solo para su dueño. A los demás les responde 404, sin desvelar que existe. */
    public function miniatura(Request $request, string $idioma, string $diseno): Response
    {
        $diseno = $this->delVisitante($request, $diseno);

        return Storage::disk(GuardarDiseno::DISCO)->response($diseno->miniatura, null, [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** @return array<string, mixed> */
    public static function datos(Diseno $diseno): array
    {
        $avisos = [];
        if ($diseno->fondo !== 'transparente') {
            $avisos[] = __('disenos.'.$diseno->fondo);
        }
        if ($diseno->perfil_color !== null && ! in_array($diseno->perfil_color, ['sRGB', 'Gris'], true) && ! str_contains($diseno->perfil_color, 'sRGB')) {
            $avisos[] = __('disenos.aviso_perfil', ['perfil' => $diseno->perfil_color]);
        }

        return [
            'uuid' => $diseno->uuid,
            'nombre' => $diseno->nombre_original,
            'ancho_px' => $diseno->util_ancho_px,
            'alto_px' => $diseno->util_alto_px,
            'fondo' => $diseno->fondo,
            'miniatura' => route('disenos.miniatura', ['diseno' => $diseno->uuid]),
            'avisos' => $avisos,
        ];
    }
}
