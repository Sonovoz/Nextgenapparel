<?php

namespace App\Http\Controllers;

use App\Dominio\Disenos\AnalizadorPng;
use App\Dominio\Disenos\DisenoNoValido;
use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Disenos\Propietario;
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
        $maxMb = AnalizadorPng::limites()['max_mb'];

        $request->validate([
            'archivo' => ['required', 'file', 'max:'.($maxMb * 1024)],
            'derechos' => ['accepted'],
        ], [
            'archivo.required' => __('disenos.error_sin_archivo'),
            'archivo.file' => __('disenos.error_subida', ['mb' => $maxMb]),
            'archivo.uploaded' => __('disenos.error_subida', ['mb' => $maxMb]),
            'archivo.max' => __('disenos.error_pesado', ['mb' => $maxMb]),
            'derechos.accepted' => __('disenos.error_derechos'),
        ]);

        try {
            $diseno = $guardar($request->file('archivo'), Propietario::hash($request));
        } catch (DisenoNoValido $error) {
            return response()->json(['message' => $error->mensaje(), 'motivo' => $error->motivo], 422);
        }

        return response()->json(self::datos($diseno), $diseno->wasRecentlyCreated ? 201 : 200);
    }

    /** Miniatura de un diseño, solo para su dueño. A los demás les responde 404, sin desvelar que existe. */
    public function miniatura(Request $request, string $idioma, string $diseno): Response
    {
        $propietario = Propietario::hash($request, crear: false);
        abort_if($propietario === null, 404);

        $diseno = Diseno::where('uuid', $diseno)->where('propietario', $propietario)->firstOrFail();

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
