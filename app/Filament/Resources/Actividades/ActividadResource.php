<?php

namespace App\Filament\Resources\Actividades;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Filament\Resources\Actividades\Pages\ListActividades;
use App\Filament\Resources\Actividades\Pages\ViewActividad;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActividadResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Registro de actividad';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'actividad';

    protected static ?string $pluralModelLabel = 'actividad';

    protected static ?string $slug = 'registro-actividad';

    /** Nombres legibles de cada registro. */
    public const REGISTROS = [
        'usuarios' => 'Usuarios',
        'accesos' => 'Accesos',
        'errores' => 'Errores',
        'copias' => 'Copias de seguridad',
        'catalogo' => 'Catálogo',
        'ajustes' => 'Ajustes',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['causer', 'subject']))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Todavía no hay actividad registrada')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->placeholder('Sistema')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Acción')
                    ->description(fn (Activity $record): ?string => Actividad::resumen($record))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('elemento')
                    ->label('Elemento')
                    ->state(fn (Activity $record): ?string => Actividad::elemento($record))
                    ->placeholder('—'),
                TextColumn::make('log_name')
                    ->label('Registro')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::REGISTROS[$state] ?? (string) $state),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Registro')
                    ->options(self::REGISTROS),
                SelectFilter::make('causer_id')
                    ->label('Usuario')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->where('causer_type', User::class)->where('causer_id', $data['value'])
                        : $query),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('description')->label('Acción'),
                        TextEntry::make('causer.name')->label('Usuario')->placeholder('Sistema'),
                        TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('elemento')
                            ->label('Elemento')
                            ->state(fn (Activity $record): ?string => Actividad::elemento($record))
                            ->placeholder('—'),
                        TextEntry::make('log_name')
                            ->label('Registro')
                            ->formatStateUsing(fn (?string $state): string => self::REGISTROS[$state] ?? (string) $state),
                    ]),
                Section::make('Cambios')
                    ->visible(fn (Activity $record): bool => Actividad::cambios($record) !== [])
                    ->schema([
                        KeyValueEntry::make('cambios')
                            ->hiddenLabel()
                            ->keyLabel('Campo')
                            ->valueLabel('Antes → Después')
                            ->state(fn (Activity $record): array => collect(Actividad::cambios($record))
                                ->map(fn (array $v) => $v[0].' → '.$v[1])
                                ->all()),
                    ]),
                Section::make('Datos')
                    ->visible(fn (Activity $record): bool => filled($record->properties->except(['attributes', 'old'])->all()))
                    ->schema([
                        KeyValueEntry::make('datos')
                            ->hiddenLabel()
                            ->keyLabel('Dato')
                            ->valueLabel('Valor')
                            ->state(fn (Activity $record): array => $record->properties->except(['attributes', 'old'])
                                ->map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE))
                                ->all()),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActividades::route('/'),
            'view' => ViewActividad::route('/{record}'),
        ];
    }
}
