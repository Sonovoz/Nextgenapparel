<?php

namespace App\Filament\Resources\Catalogo\Prendas\RelationManagers;

use App\Dominio\Catalogo\GenerarVariantes;
use App\Filament\Concerns\CampoDinero;
use App\Models\Color;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\Variante;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/** Variaciones color × talla con su SKU automático, precio, coste, peso y stock. */
class VariantesRelationManager extends RelationManager
{
    protected static string $relationship = 'variantes';

    protected static ?string $title = 'Variaciones';

    protected static ?string $modelLabel = 'variación';

    protected static ?string $pluralModelLabel = 'variaciones';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('sku')->label('SKU')->disabled()->dehydrated(false)->helperText('Automático'),
            CampoDinero::make('precio_base', 'Precio sin IVA')->required(),
            CampoDinero::make('coste', 'Coste sin IVA')->required(),
            TextInput::make('peso_gramos')->label('Peso')->numeric()->integer()->minValue(1)->maxValue(5000)->suffix('g')->required(),
            TextInput::make('stock')->label('Stock')->numeric()->integer()->minValue(0)->required(),
            TextInput::make('stock_minimo')->label('Aviso de stock bajo')->numeric()->integer()->minValue(0)->default(0),
            Toggle::make('activa')->label('Activa')->inline(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['color', 'talla'])->join('tallas', 'tallas.id', '=', 'variantes.talla_id')->join('colores', 'colores.id', '=', 'variantes.color_id')->orderBy('colores.orden')->orderBy('tallas.orden')->select('variantes.*'))
            ->emptyStateHeading('Sin variaciones')
            ->emptyStateDescription('Pulsa «Generar variaciones» y elige colores y tallas.')
            ->columns([
                ColorColumn::make('color.codigo_hex')->label(''),
                TextColumn::make('color.nombre')->label('Color'),
                TextColumn::make('talla.nombre')->label('Talla'),
                TextColumn::make('sku')->label('SKU')->fontFamily('mono')->searchable()->copyable(),
                TextColumn::make('precio_base')->label('Precio sin IVA')->formatStateUsing(fn (int $state) => CampoDinero::formatear($state)),
                TextColumn::make('coste')->label('Coste')->formatStateUsing(fn (int $state) => CampoDinero::formatear($state)),
                TextColumn::make('peso_gramos')->label('Peso')->suffix(' g'),
                TextColumn::make('stock')->label('Stock')->badge()->color(fn (Variante $r) => $r->stockBajo() ? 'danger' : 'gray'),
                ToggleColumn::make('activa')->label('Activa'),
            ])
            ->filters([
                SelectFilter::make('color_id')->label('Color')->options(fn () => Color::orderBy('orden')->get()->pluck('nombre', 'id')),
            ])
            ->headerActions([$this->accionGenerar()])
            // Sin borrar: los pedidos guardarán la variación. Para retirarla, se desactiva (D109).
            ->recordActions([EditAction::make()])
            ->toolbarActions([$this->accionCambiarPrecio()]);
    }

    private function accionGenerar(): Action
    {
        return Action::make('generar')
            ->label('Generar variaciones')
            ->icon(Heroicon::OutlinedSquaresPlus)
            ->modalDescription('Crea las combinaciones color × talla que falten. Las que ya existen no se tocan.')
            ->schema([
                CheckboxList::make('colores')->label('Colores')->required()->columns(4)
                    ->options(fn () => Color::where('activo', true)->orderBy('orden')->get()->pluck('nombre', 'id')),
                CheckboxList::make('tallas')->label('Tallas')->required()->columns(7)
                    ->options(fn () => Talla::orderBy('orden')->pluck('nombre', 'id')),
                Grid::make(4)->schema([
                    CampoDinero::make('precio_base', 'Precio sin IVA')->required(),
                    CampoDinero::make('coste', 'Coste sin IVA')->required(),
                    TextInput::make('peso_gramos')->label('Peso')->numeric()->integer()->minValue(1)->maxValue(5000)->suffix('g')->required(),
                    TextInput::make('stock')->label('Stock inicial')->numeric()->integer()->minValue(0)->default(0)->required(),
                ]),
            ])
            ->action(function (array $data): void {
                /** @var Prenda $prenda */
                $prenda = $this->getOwnerRecord();
                $creadas = app(GenerarVariantes::class)(
                    $prenda,
                    array_map('intval', $data['colores']),
                    array_map('intval', $data['tallas']),
                    (int) $data['precio_base'],
                    (int) $data['coste'],
                    (int) $data['peso_gramos'],
                    (int) $data['stock'],
                );

                Notification::make()
                    ->title($creadas === 0 ? 'No faltaba ninguna variación' : "{$creadas} ".($creadas === 1 ? 'variación creada' : 'variaciones creadas'))
                    ->success()->send();
            });
    }

    private function accionCambiarPrecio(): BulkAction
    {
        return BulkAction::make('cambiarPrecio')
            ->label('Cambiar precio')
            ->icon(Heroicon::OutlinedCurrencyEuro)
            ->schema([CampoDinero::make('precio_base', 'Nuevo precio sin IVA')->required()])
            ->action(function (Collection $records, array $data): void {
                $records->each(fn (Model $v) => $v->update(['precio_base' => (int) $data['precio_base']]));
                Notification::make()->title('Precio actualizado en '.$records->count().' variaciones')->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
