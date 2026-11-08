<?php

namespace App\Http\Controllers\Backend;

use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\Diseno;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Archivos de la biblioteca de diseños para el backend: miniatura y original.
 * Están en el disco privado; solo los sirve quien tiene permiso (DisenoPolicy).
 * A los demás, 404, sin desvelar que el diseño existe.
 */
class ArchivoDisenoController
{
    public function miniatura(Diseno $diseno): Response
    {
        abort_unless(auth()->user()?->can('view', $diseno), 404);

        return Storage::disk(GuardarDiseno::DISCO)->response($diseno->miniatura, null, [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function original(Diseno $diseno): Response
    {
        abort_unless(auth()->user()?->can('view', $diseno), 404);

        Actividad::apuntar('disenos', 'Original de un diseño descargado', $diseno);

        return Storage::disk(GuardarDiseno::DISCO)->download($diseno->ruta, $diseno->nombre_original, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
