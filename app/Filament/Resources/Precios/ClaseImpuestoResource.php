<?php

namespace App\Filament\Resources\Precios;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Concerns\CampoPorcentaje;
use App\Filament\Concerns\CamposTraducibles;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\ClaseImpuesto;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Ajustes → Impuestos. */
class ClaseImpuestoResource extends RecursoPrecios
{
    protected static ?string $model = ClaseImpuesto::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'Impuestos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'clase de impuesto';

    protected static ?string $pluralModelLabel = 'clases de impuesto';

    protected static ?string $slug = 'impuestos';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            CamposTraducibles::texto('nombre', 'Nombre', obligatorio: true),
            Grid::make(4)->schema([
                TextInput::make('clave')->label('Clave')->required()->maxLength(40)->rule('regex:/^[a-z0-9_]+$/')->unique(ignoreRecord: true)
                    ->helperText('Interna: general, reducido…'),
                CampoPorcentaje::make('tipo_iva', 'IVA')->required(),
                CampoPorcentaje::make('recargo', 'Recargo de equivalencia')->required()->default(0),
                Toggle::make('por_defecto')->label('Por defecto')->inline(false)
                    ->helperText('La usan las prendas sin clase y el envío.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('orden')->reorderable('orden')
            ->description('Como en WooCommerce: cada prenda o pack usa su clase de impuesto o la de por defecto. Los precios se guardan sin IVA.')
            ->columns([
                TextColumn::make('nombre')->label('Nombre'),
                TextColumn::make('tipo_iva')->label('IVA')->formatStateUsing(fn ($state) => CampoPorcentaje::formatear((int) $state)),
                TextColumn::make('recargo')->label('Recargo')->formatStateUsing(fn ($state) => CampoPorcentaje::formatear((int) $state)),
                IconColumn::make('por_defecto')->label('Por defecto')->boolean(),
            ])
            ->recordActions([static::accionEditar()->after(fn (ClaseImpuesto $record) => static::unicaPorDefecto($record)), static::accionBorrar()]);
    }

    /** Solo una clase puede ser la de por defecto. */
    public static function unicaPorDefecto(ClaseImpuesto $clase): void
    {
        if ($clase->por_defecto) {
            ClaseImpuesto::whereKeyNot($clase->getKey())->update(['por_defecto' => false]);
        }
        Actividad::apuntar('ajustes', 'Impuestos cambiados', $clase, ['attributes' => ['clase' => $clase->clave, 'iva' => $clase->tipo_iva, 'recargo' => $clase->recargo]]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageClaseImpuesto::route('/')];
    }
}
