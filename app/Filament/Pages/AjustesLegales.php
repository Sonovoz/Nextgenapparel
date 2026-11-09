<?php

namespace App\Filament\Pages;

use App\Dominio\Legal\GenerarTextosLegales;
use App\Dominio\Legal\PaginaLegal;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\TextoLegal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Ajustes → Textos legales (paso 8.1, D183): la última versión de cada texto, lo que le falta y su
 * revisión por la asesoría. Se regeneran con los datos del sistema; cada cambio es una versión nueva.
 */
class AjustesLegales extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.produccion-tabla';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'Textos legales';

    protected static ?string $title = 'Textos legales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'ajustes-legales';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Ajustes->permiso());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerar')->label('Regenerar textos')->icon(Heroicon::OutlinedArrowPath)
                ->action(function (GenerarTextosLegales $generar): void {
                    $n = $generar();
                    Actividad::apuntar('ajustes', 'Textos legales regenerados', propiedades: ['versiones_nuevas' => $n]);
                    Notification::make()->title($n === 0 ? 'Sin cambios: los textos ya estaban al día' : "{$n} ".($n === 1 ? 'versión nueva' : 'versiones nuevas'))->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => TextoLegal::query()->whereIn('id', TextoLegal::query()->selectRaw('max(id)')->groupBy('pagina', 'idioma'))->orderBy('pagina')->orderBy('idioma'))
            ->emptyStateHeading('Aún no hay textos')->emptyStateDescription('Pulsa «Regenerar textos».')
            ->paginated(false)
            ->columns([
                TextColumn::make('pagina')->label('Texto')->formatStateUsing(fn (PaginaLegal $state) => $state->titulo('es'))
                    ->description(fn (TextoLegal $r) => $r->idioma === 'es' ? 'Castellano' : 'Inglés')
                    ->url(fn (TextoLegal $r) => route('legal', ['idioma' => $r->idioma, 'pagina' => $r->pagina->value]), shouldOpenInNewTab: true),
                TextColumn::make('version')->label('Versión')->formatStateUsing(fn (int $state, TextoLegal $r) => 'v'.$state.' · '.$r->generado_en->timezone('Europe/Madrid')->format('d/m/Y')),
                TextColumn::make('pendientes')->label('Falta')->badge()->color('warning')->placeholder('Nada')
                    ->state(fn (TextoLegal $r) => $r->pendientes ?? []),
                TextColumn::make('revisado_en')->label('Asesoría')->badge()
                    ->state(fn (TextoLegal $r) => $r->revisado_en === null ? 'Borrador' : 'Revisado · '.$r->revisado_por)
                    ->color(fn (TextoLegal $r) => $r->revisado_en === null ? 'gray' : 'success'),
            ])
            ->recordActions([
                Action::make('revisado')->label('Marcar revisado')->icon(Heroicon::OutlinedCheckBadge)
                    ->visible(fn (TextoLegal $r) => $r->revisado_en === null)
                    ->requiresConfirmation()->modalDescription('Confirma que la asesoría ha revisado esta versión. Queda anotado quién y cuándo.')
                    ->action(function (TextoLegal $r): void {
                        $r->update(['revisado_en' => now(), 'revisado_por' => (string) auth()->user()?->name]);
                        Actividad::apuntar('ajustes', 'Texto legal marcado como revisado: '.$r->etiqueta());
                    }),
            ]);
    }
}
