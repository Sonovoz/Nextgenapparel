<?php

namespace App\Filament\Pages;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Dominio\Sistema\Copias\Copias;
use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Herramientas → Copias de seguridad. La restauración completa se hace con
 * el procedimiento de docs/RESTAURACION.md, no desde aquí (D26).
 */
class CopiasSeguridad extends Page
{
    protected string $view = 'filament.pages.copias-seguridad';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Copias de seguridad';

    protected static ?string $title = 'Copias de seguridad';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'copias-seguridad';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Herramientas->permiso());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('probar')
                ->label('Probar restauración')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray')
                ->action(function (Copias $copias): void {
                    $resultado = $copias->probarRestauracion();
                    $aviso = Notification::make()->title($resultado['correcto'] ? 'La copia se puede restaurar' : 'La prueba ha fallado')->body($resultado['mensaje']);
                    $resultado['correcto'] ? $aviso->success()->send() : $aviso->danger()->send();
                }),
            Action::make('hacerCopia')
                ->label('Hacer copia ahora')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->requiresConfirmation()
                ->modalDescription('Copia la base de datos y los archivos. Puede tardar unos segundos.')
                ->modalSubmitActionLabel('Hacer copia')
                ->action(function (Copias $copias): void {
                    $error = $copias->hacer();
                    Actividad::apuntar('copias', $error === null ? 'Copia de seguridad hecha a mano' : 'Copia de seguridad a mano fallida');

                    $error === null
                        ? Notification::make()->title('Copia hecha')->success()->send()
                        : Notification::make()->title('La copia ha fallado')->body($error)->danger()->send();
                }),
        ];
    }

    /** Descarga de una copia: solo el superadministrador. */
    public function descargar(string $disco, string $ruta): ?StreamedResponse
    {
        abort_unless(auth()->user()?->esSuperadministrador(), 403);

        $copia = collect(Copias::listar())->first(fn ($c) => $c['disco'] === $disco && $c['ruta'] === $ruta);
        abort_if($copia === null, 404);

        Actividad::apuntar('copias', 'Copia de seguridad descargada', propiedades: ['copia' => $copia['nombre'], 'disco' => $disco]);

        return Storage::disk($disco)->download($ruta);
    }

    protected function getViewData(): array
    {
        return [
            'copias' => Copias::listar(),
            'prueba' => Copias::ultimaPrueba(),
            'cifradas' => filled(config('backup.backup.password')),
            'externa' => in_array('copias_externas', config('backup.backup.destination.disks'), true),
            'puedeDescargar' => (bool) auth()->user()?->esSuperadministrador(),
        ];
    }
}
