<?php

namespace App\Filament\Resources\Campanas;

use App\Dominio\Marketing\Campanas;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Filament\Resources\Campanas\Pages\CreateCampana;
use App\Filament\Resources\Campanas\Pages\EditCampana;
use App\Filament\Resources\Campanas\Pages\ListCampanas;
use App\Models\Campana;
use App\Models\Cupon;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Marketing → Campañas (v2, D190): correos de temporada por segmento, con cupón opcional. Se envían una vez. */
class CampanaResource extends Resource
{
    protected static ?string $model = Campana::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Marketing;

    protected static ?string $navigationLabel = 'Campañas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'campaña';

    protected static ?string $pluralModelLabel = 'campañas';

    protected static ?string $slug = 'campanas';

    public const SEGMENTOS = ['todos' => 'Todos los clientes con consentimiento', 'con_pedidos' => 'Clientes que ya han comprado', 'sin_pedidos' => 'Clientes que aún no han comprado'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campaña')->columns(2)->columnSpanFull()
                ->description('Solo la reciben clientes con cuenta, correo verificado y consentimiento comercial, con enlace de baja.')
                ->schema([
                    TextInput::make('nombre')->label('Nombre interno')->required()->maxLength(120),
                    Select::make('segmento')->label('A quién')->options(self::SEGMENTOS)->required()->native(false)->default('todos'),
                    TextInput::make('asunto.es')->label('Asunto (es)')->required()->maxLength(120),
                    TextInput::make('asunto.en')->label('Asunto (en)')->required()->maxLength(120),
                    Textarea::make('texto.es')->label('Texto (es)')->required()->rows(6)->maxLength(3000),
                    Textarea::make('texto.en')->label('Texto (en)')->required()->rows(6)->maxLength(3000),
                    Select::make('cupon')->label('Cupón (opcional)')->native(false)->placeholder('Sin cupón')
                        ->options(fn () => Cupon::where('activo', true)->orderBy('codigo')->pluck('codigo', 'codigo')->all()),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Sin campañas')
            ->columns([
                TextColumn::make('nombre')->label('Campaña')->weight('bold')->searchable(),
                TextColumn::make('segmento')->label('A quién')->formatStateUsing(fn (string $state) => self::SEGMENTOS[$state] ?? $state),
                TextColumn::make('cupon')->label('Cupón')->placeholder('—'),
                TextColumn::make('enviada_en')->label('Enviada')->dateTime('d/m/Y H:i')->placeholder('Borrador'),
                TextColumn::make('enviados')->label('Correos')->alignEnd(),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (Campana $r) => $r->enviada_en === null),
                Action::make('enviar')->label('Enviar')->icon(Heroicon::OutlinedPaperAirplane)->color('success')
                    ->visible(fn (Campana $r) => $r->enviada_en === null)
                    ->requiresConfirmation()->modalDescription(fn (Campana $r) => 'Se envía ahora a '.Campanas::destinatarios()->count().' clientes como mucho (según el segmento). No se puede deshacer.')
                    ->action(function (Campana $r): void {
                        $n = app(Campanas::class)->campana($r);
                        Actividad::apuntar('marketing', 'Campaña enviada: '.$r->nombre, propiedades: ['enviados' => $n]);
                        Notification::make()->title("Campaña enviada a {$n} clientes")->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCampanas::route('/'), 'create' => CreateCampana::route('/crear'), 'edit' => EditCampana::route('/{record}/editar')];
    }
}
