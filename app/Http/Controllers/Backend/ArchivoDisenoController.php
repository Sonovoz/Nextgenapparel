<?php

namespace App\Http\Controllers\Backend;

use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\Diseno;
use Filament\Facades\Filament;
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
        $this->comprobarAcceso($diseno);

        return Storage::disk(GuardarDiseno::DISCO)->response($diseno->miniatura, null, [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function original(Diseno $diseno): Response
    {
        $this->comprobarAcceso($diseno);

        Actividad::apuntar('disenos', 'Original de un diseño descargado', $diseno);

        // Siempre .png: un archivo llamado «x.html» o «x.svg» no se abre en el equipo del taller como tal.
        $nombre = (pathinfo($diseno->nombre_original, PATHINFO_FILENAME) ?: 'diseno').'.png';

        return Storage::disk(GuardarDiseno::DISCO)->download($diseno->ruta, $nombre, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Las mismas condiciones que el panel (usuario activo y con rol) más el permiso del diseño:
     * estas rutas no pasan por el middleware de Filament y un empleado desactivado con la
     * sesión abierta no debe descargar nada.
     */
    private function comprobarAcceso(Diseno $diseno): void
    {
        $usuario = auth()->user();

        abort_unless($usuario !== null && $usuario->canAccessPanel(Filament::getPanel('admin')) && $usuario->can('view', $diseno), 404);
    }
}
