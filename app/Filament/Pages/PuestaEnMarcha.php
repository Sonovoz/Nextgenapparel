<?php

namespace App\Filament\Pages;

use App\Dominio\Sistema\PuestaEnMarcha\Paso;
use App\Dominio\Sistema\PuestaEnMarcha\PuestaEnMarcha as Pasos;
use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/** Herramientas → Puesta en marcha (D196): lo que falta para abrir la tienda, comprobado solo, con enlace a dónde se hace. */
class PuestaEnMarcha extends Page
{
    protected string $view = 'filament.pages.puesta-en-marcha';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Puesta en marcha';

    protected static ?string $title = 'Puesta en marcha';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'puesta-en-marcha';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Herramientas->permiso());
    }

    /** Lo que falta, en el menú (calculado como mucho cada 5 minutos para no cargar cada página del panel). */
    public static function getNavigationBadge(): ?string
    {
        $faltan = Cache::remember('puesta-en-marcha.pendientes', 300, fn () => Pasos::pendientes());

        return $faltan > 0 ? (string) $faltan : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $pasos = Pasos::pasos();
        Cache::put('puesta-en-marcha.pendientes', collect($pasos)->filter(fn (Paso $p) => ! $p->hecho && ! $p->opcional)->count(), 300);

        return [
            'pasos' => array_map(fn (Paso $p) => ['paso' => $p, 'url' => self::url($p)], $pasos),
            'hechos' => collect($pasos)->filter(fn (Paso $p) => $p->hecho && ! $p->opcional)->count(),
            'total' => collect($pasos)->reject(fn (Paso $p) => $p->opcional)->count(),
        ];
    }

    /** Enlace a donde se hace, si quien mira puede entrar. */
    private static function url(Paso $paso): ?string
    {
        $clase = $paso->donde;
        if ($clase === null || ! $clase::canAccess()) {
            return null;
        }

        return is_subclass_of($clase, Resource::class) ? $clase::getUrl('index') : $clase::getUrl();
    }
}
