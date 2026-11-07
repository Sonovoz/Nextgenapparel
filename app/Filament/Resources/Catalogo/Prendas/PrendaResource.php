<?php

namespace App\Filament\Resources\Catalogo\Prendas;

use App\Filament\Concerns\CampoDinero;
use App\Filament\Concerns\CamposTraducibles;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Prenda;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/** Productos → Todos los productos (productos variables de WooCommerce). */
class PrendaResource extends Resource
{
    protected static ?string $model = Prenda::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Productos;

    protected static ?string $navigationLabel = 'Todos los productos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'prenda';

    protected static ?string $pluralModelLabel = 'prendas';

    protected static ?string $slug = 'productos';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Prenda')->schema([
                Grid::make(3)->schema([
                    Select::make('familia_id')->label('Familia')->relationship('familia', 'id')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->nombre)->required()->native(false)->preload(),
                    Select::make('proveedor_id')->label('Proveedor')->relationship('proveedor', 'nombre')->native(false)->preload(),
                    TextInput::make('referencia_proveedor')->label('Referencia del proveedor')->maxLength(100),
                ]),
                CamposTraducibles::texto('nombre', 'Nombre', obligatorio: true, slugDe: 'slug'),
                Grid::make(2)->schema([
                    TextInput::make('slug.es')->label('URL (español)')->required()->alphaDash()->maxLength(160)
                        ->unique(table: 'prendas', column: 'slug->es', ignoreRecord: true),
                    TextInput::make('slug.en')->label('URL (inglés)')->alphaDash()->maxLength(160)
                        ->unique(table: 'prendas', column: 'slug->en', ignoreRecord: true),
                ]),
                CamposTraducibles::texto('descripcion', 'Descripción', largo: true),
            ]),
            Section::make('Detalles técnicos')->schema([
                CamposTraducibles::texto('composicion', 'Composición'),
                Grid::make(2)->schema([
                    TextInput::make('gramaje')->label('Gramaje')->numeric()->integer()->minValue(50)->maxValue(1000)->suffix('g/m²'),
                    Toggle::make('activa')->label('Visible en la tienda')->default(true)->inline(false),
                ]),
                CamposTraducibles::texto('detalles', 'Otros detalles (corte, cuidados…)', largo: true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('familia')->withCount('variantes')->withMin(['variantes' => fn ($v) => $v->where('activa', true)], 'precio_base')->withSum('variantes', 'stock'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nombre')->label('Nombre')->searchable(query: fn (Builder $q, string $buscar) => $q->where('nombre->es', 'like', "%{$buscar}%")->orWhere('nombre->en', 'like', "%{$buscar}%")),
                TextColumn::make('familia.nombre')->label('Familia'),
                TextColumn::make('variantes_count')->label('Variaciones'),
                TextColumn::make('variantes_min_precio_base')->label('Desde (sin IVA)')->formatStateUsing(fn ($state) => CampoDinero::formatear($state === null ? null : (int) $state)),
                TextColumn::make('variantes_sum_stock')->label('Stock')->numeric(decimalPlaces: 0, thousandsSeparator: '.'),
                IconColumn::make('activa')->label('Visible')->boolean(),
            ])
            ->filters([
                SelectFilter::make('familia_id')->label('Familia')->relationship('familia', 'id')->getOptionLabelFromRecordUsing(fn ($record) => $record->nombre),
                TrashedFilter::make()->label('Papelera'),
            ])
            ->recordActions([EditAction::make(), RestoreAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VariantesRelationManager::class,
            RelationManagers\ZonasRelationManager::class,
            RelationManagers\MedidasRelationManager::class,
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrendas::route('/'),
            'create' => Pages\CreatePrenda::route('/nueva'),
            'edit' => Pages\EditPrenda::route('/{record}/editar'),
        ];
    }
}
