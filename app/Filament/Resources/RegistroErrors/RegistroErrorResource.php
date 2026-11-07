<?php

namespace App\Filament\Resources\RegistroErrors;

use App\Dominio\Sistema\Errores\EstadoError;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Filament\Resources\RegistroErrors\Pages\ListRegistroErrors;
use App\Filament\Resources\RegistroErrors\Pages\ViewRegistroError;
use App\Filament\Resources\RegistroErrors\Schemas\RegistroErrorInfolist;
use App\Filament\Resources\RegistroErrors\Tables\RegistroErrorsTable;
use App\Models\RegistroError;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RegistroErrorResource extends Resource
{
    protected static ?string $model = RegistroError::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Log de errores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'error';

    protected static ?string $pluralModelLabel = 'errores';

    protected static ?string $slug = 'log-errores';

    /**
     * Número de errores pendientes junto al enlace del menú. Si no se puede
     * contar (tabla sin crear, base de datos caída), no se muestra: el menú
     * nunca debe impedir usar el backend.
     */
    public static function getNavigationBadge(): ?string
    {
        $pendientes = rescue(
            fn (): int => RegistroError::where('estado', EstadoError::Pendiente)->count(),
            0,
            report: false,
        );

        return $pendientes > 0 ? (string) $pendientes : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return 'Errores pendientes';
    }

    public static function infolist(Schema $schema): Schema
    {
        return RegistroErrorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RegistroErrorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegistroErrors::route('/'),
            'view' => ViewRegistroError::route('/{record}'),
        ];
    }
}
