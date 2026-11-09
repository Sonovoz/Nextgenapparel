<?php

namespace App\Filament\Pages;

use App\Dominio\Pagos\Pasarela;
use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Ajustes → Pagos (fase 7, D181): qué pasarela cobra, en qué entorno y con qué métodos, y la URL
 * de notificación para el banco. Los datos del TPV van solo en el .env del servidor (regla 10):
 * aquí se ven, nunca se escriben ni se enseña la clave.
 */
class AjustesPagos extends Page
{
    protected string $view = 'filament.pages.ajustes-pagos';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'Pagos';

    protected static ?string $title = 'Pagos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'ajustes-pagos';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Ajustes->permiso());
    }

    protected function getViewData(): array
    {
        $pasarela = app(Pasarela::class);
        $redsys = (array) config('pagos.redsys');

        return [
            'pasarela' => $pasarela->nombre(),
            'pruebas' => $pasarela->pruebas(),
            'metodos' => $pasarela->metodos(),
            'comercio' => filled($redsys['comercio'] ?? null) ? $redsys['comercio'].' · terminal '.$redsys['terminal'] : null,
            'clave' => filled($redsys['clave'] ?? null),
            'aviso' => route('pagos.aviso'),
        ];
    }
}
