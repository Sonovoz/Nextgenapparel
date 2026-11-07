<?php

namespace App\Filament\Pages;

use App\Dominio\Sistema\Salud\ComprobarSalud;
use App\Dominio\Sistema\Salud\EstadoSalud;
use App\Dominio\Sistema\Salud\Resultado;
use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Cron\CronExpression;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use UnitEnum;

/**
 * Herramientas → Estado del sistema (como «Estado» de WooCommerce).
 */
class EstadoSistema extends Page
{
    protected string $view = 'filament.pages.estado-sistema';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Estado del sistema';

    protected static ?string $title = 'Estado del sistema';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'estado-sistema';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Herramientas->permiso());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('comprobarAhora')
                ->label('Comprobar ahora')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    $resultados = app(ComprobarSalud::class)(avisar: false);
                    $fallos = count(array_filter($resultados, fn (Resultado $r) => $r->estado === EstadoSalud::Fallo));

                    $aviso = Notification::make()->title($fallos === 0 ? 'Todo funciona' : "{$fallos} ".($fallos === 1 ? 'comprobación falla' : 'comprobaciones fallan'));
                    $fallos === 0 ? $aviso->success()->send() : $aviso->danger()->send();
                }),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'resultados' => ComprobarSalud::ultimosResultados(),
            'comprobadoEn' => ComprobarSalud::ultimaComprobacion(),
            'tareas' => collect(self::tareasProgramadas())
                ->filter(fn (Event $evento) => $evento->runsInEnvironment(app()->environment()))
                ->map(fn (Event $evento) => [
                    'descripcion' => $evento->description ?? $evento->command,
                    'frecuencia' => self::frecuencia($evento->expression),
                    'siguiente' => $evento->nextRunDate()->setTimezone(config('app.timezone')),
                ])
                ->values(),
            'esLocal' => app()->isLocal(),
        ];
    }

    /**
     * Laravel solo carga routes/console.php al ejecutar comandos; en una
     * página web la lista de tareas está vacía y hay que cargarla.
     *
     * @return list<Event>
     */
    public static function tareasProgramadas(): array
    {
        $tareas = app(Schedule::class);

        if ($tareas->events() === []) {
            require base_path('routes/console.php');
        }

        return $tareas->events();
    }

    /** Frecuencia de una expresión cron en palabras. */
    public static function frecuencia(string $cron): string
    {
        if ($cron === '* * * * *') {
            return 'Cada minuto';
        }

        if (preg_match('/^\*\/(\d+) \* \* \* \*$/', $cron, $m)) {
            return "Cada {$m[1]} minutos";
        }

        if (preg_match('/^(\d+) (\d+) \* \* \*$/', $cron, $m)) {
            return sprintf('Cada día a las %02d:%02d', $m[2], $m[1]);
        }

        return CronExpression::isValidExpression($cron) ? $cron : '—';
    }
}
