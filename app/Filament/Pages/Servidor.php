<?php

namespace App\Filament\Pages;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Dominio\Sistema\Servidor\InformeServidor;
use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Throwable;
use UnitEnum;

/**
 * Herramientas → Servidor (D207): PHP, extensiones, base de datos y Redis del servidor, sin contraseñas.
 * Dos arreglos sin consola: crear el enlace de las fotos y recargar el .env tras editarlo con filebrowser.
 */
class Servidor extends Page
{
    protected string $view = 'filament.pages.servidor';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Servidor';

    protected static ?string $title = 'Servidor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'servidor';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Herramientas->permiso());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enlace')
                ->label('Crear el enlace de las fotos')
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn () => ! InformeServidor::enlaceCorrecto())
                ->requiresConfirmation()
                ->modalDescription('Crea public/storage, por donde se ven las fotos de las prendas y las maquetas (lo mismo que «php artisan storage:link»).')
                ->action(fn () => $this->ejecutar('Enlace de las fotos creado', function (): void {
                    $enlace = public_path('storage');
                    if (is_link($enlace)) {
                        unlink($enlace); // roto o hacia otro sitio (por ejemplo, copiado de otro ordenador)
                    }
                    Artisan::call('storage:link');
                    if (! InformeServidor::enlaceCorrecto()) {
                        throw new \RuntimeException(is_dir($enlace) ? 'public/storage es una carpeta normal: bórrala con filebrowser y vuelve a pulsar.' : trim(Artisan::output()));
                    }
                })),
            Action::make('recargar')
                ->label('Recargar el .env')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Después de cambiar el .env (por ejemplo, con filebrowser), el portal vuelve a leerlo. Es lo mismo que «php artisan config:cache».')
                ->action(fn () => $this->ejecutar('.env recargado', function (): void {
                    if (app()->configurationIsCached()) {
                        Artisan::call('config:cache');
                    }
                })),
        ];
    }

    private function ejecutar(string $hecho, callable $paso): void
    {
        try {
            $paso();
        } catch (Throwable $e) {
            Notification::make()->danger()->title('No se ha podido')->body($e->getMessage())->persistent()->send();

            return;
        }
        Actividad::apuntar('sistema', $hecho.' desde Herramientas → Servidor');
        Notification::make()->success()->title($hecho)->send();
        $this->redirect(static::getUrl());
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['grupos' => collect(InformeServidor::filas())->groupBy('grupo')];
    }
}
