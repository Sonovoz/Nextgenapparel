<?php

namespace App\Filament;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatar con las iniciales del usuario, generado en el propio servidor.
 *
 * Sustituye al proveedor por defecto de Filament, que pide la imagen a
 * ui-avatars.com y envía así el nombre de cada administrador a un tercero (D35).
 */
class AvatarIniciales implements AvatarProvider
{
    public function get(Model $record): string
    {
        $iniciales = collect(preg_split('/\s+/u', trim(Filament::getNameForDefaultAvatar($record))) ?: [])
            ->map(fn (string $palabra): string => (string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $palabra))
            ->filter()
            ->take(2)
            ->map(fn (string $palabra): string => mb_strtoupper(mb_substr($palabra, 0, 1)))
            ->join('');

        $texto = htmlspecialchars($iniciales !== '' ? $iniciales : '?', ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#18181B"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#FFFFFF" '
            .'font-family="Inter, system-ui, sans-serif" font-size="26" font-weight="600">'.$texto.'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
