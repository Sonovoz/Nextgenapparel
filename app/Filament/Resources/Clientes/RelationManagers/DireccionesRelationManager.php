<?php

namespace App\Filament\Resources\Clientes\RelationManagers;

use App\Dominio\Clientes\Direcciones;
use App\Dominio\Clientes\Paises;
use App\Dominio\Clientes\ReglasDireccion;
use App\Dominio\Clientes\TipoDireccion;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\Direccion;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Direcciones del cliente (paso 5.4), con las mismas reglas que su área (una predeterminada por tipo). */
class DireccionesRelationManager extends RelationManager
{
    protected static string $relationship = 'direcciones';

    protected static ?string $title = 'Direcciones';

    protected static ?string $modelLabel = 'dirección';

    protected static ?string $pluralModelLabel = 'direcciones';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('tipo')->label('Tipo')->options(TipoDireccion::class)->required()->native(false),
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(150),
            TextInput::make('empresa')->label('Empresa')->maxLength(150),
            TextInput::make('direccion')->label('Dirección')->required()->maxLength(200),
            TextInput::make('direccion_2')->label('Piso, puerta…')->maxLength(200),
            Select::make('pais')->label('País')->options(fn () => Paises::disponibles())->required()->native(false)->default('ES')->live(),
            TextInput::make('codigo_postal')->label('Código postal')->required()->maxLength(10)
                ->rule(fn (Get $get) => function ($a, $v, $fallo) use ($get): void {
                    $tipo = $get('tipo');
                    $error = ReglasDireccion::errorCodigoPostal((string) $get('pais'), (string) $v, $tipo instanceof TipoDireccion ? $tipo->value : (string) $tipo);
                    if ($error !== null) {
                        $fallo($error);
                    }
                }),
            TextInput::make('localidad')->label('Localidad')->required()->maxLength(100),
            TextInput::make('provincia')->label('Provincia')->maxLength(100),
            TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30)->regex(ReglasDireccion::TELEFONO),
        ]);
    }

    public function table(Table $table): Table
    {
        $guardar = fn (array $datos, ?Direccion $direccion = null) => app(Direcciones::class)->guardar($this->getOwnerRecord(), [...$datos, 'codigo_postal' => strtoupper(trim($datos['codigo_postal']))], $direccion);

        return $table
            ->defaultSort('tipo')
            ->columns([
                TextColumn::make('tipo')->label('Tipo')->badge(),
                IconColumn::make('predeterminada')->label('Predeterminada')->boolean(),
                TextColumn::make('nombre')->label('Nombre'),
                TextColumn::make('direccion')->label('Dirección')->description(fn (Direccion $d) => trim($d->codigo_postal.' '.$d->localidad.' · '.Paises::nombre($d->pais))),
                TextColumn::make('telefono')->label('Teléfono')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Añadir dirección')->using(function (array $data) use ($guardar): Model {
                    $direccion = $guardar($data);
                    Actividad::apuntar('clientes', 'Dirección añadida por el equipo', $this->getOwnerRecord());

                    return $direccion;
                }),
            ])
            ->recordActions([
                Action::make('predeterminar')->label('Predeterminada')->icon('heroicon-o-star')->visible(fn (Direccion $d) => ! $d->predeterminada)
                    ->action(function (Direccion $d): void {
                        app(Direcciones::class)->predeterminar($d);
                        Actividad::apuntar('clientes', 'Dirección predeterminada cambiada por el equipo', $this->getOwnerRecord());
                    }),
                EditAction::make()->using(function (Direccion $record, array $data) use ($guardar): Model {
                    $direccion = $guardar($data, $record);
                    Actividad::apuntar('clientes', 'Dirección cambiada por el equipo', $this->getOwnerRecord());

                    return $direccion;
                }),
                DeleteAction::make()->using(function (Direccion $record): bool {
                    app(Direcciones::class)->borrar($record);
                    Actividad::apuntar('clientes', 'Dirección borrada por el equipo', $this->getOwnerRecord());

                    return true;
                }),
            ]);
    }
}
