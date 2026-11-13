<?php

namespace App\Filament\Resources\RegistroCorreos;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\RegistroCorreo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Herramientas → Registro de correos (D235): cada correo de la tienda, a quién, qué y qué le pasó. */
class RegistroCorreoResource extends Resource
{
    protected static ?string $model = RegistroCorreo::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Registro de correos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'correo';

    protected static ?string $pluralModelLabel = 'correos';

    protected static ?string $slug = 'registro-correos';

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->poll('30s')
            ->description('Cada correo que manda la tienda. «En cola» durante más de unos minutos: el cron del servidor no procesa la cola. Se guarda '.RegistroCorreo::DIAS.' días.')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('destinatario')->label('Para')->searchable()->copyable(),
                TextColumn::make('tipo')->label('Correo')->description(fn (RegistroCorreo $r) => $r->asunto)->wrap(),
                TextColumn::make('pedido.numero')->label('Pedido')->placeholder('—'),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => RegistroCorreo::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'enviado' => 'success', 'fallido' => 'danger', 'reintentando', 'en_cola', 'no_enviado' => 'warning', default => 'gray',
                    }),
                TextColumn::make('intentos')->label('Intentos')->alignCenter(),
                TextColumn::make('error')->label('Detalle')->wrap()->limit(160)->placeholder('—'),
                TextColumn::make('enviado_en')->label('Enviado')->dateTime('d/m H:i')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('estado')->label('Estado')->options(RegistroCorreo::ESTADOS),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListRegistroCorreos::route('/')];
    }
}
