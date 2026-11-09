<?php

namespace App\Filament\Resources\Clientes;

use App\Dominio\Clientes\Nif;
use App\Dominio\Clientes\ReglasDireccion;
use App\Dominio\Clientes\TipoCliente;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Filament\Resources\Clientes\Pages\EditCliente;
use App\Filament\Resources\Clientes\Pages\ListClientes;
use App\Models\Cliente;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Clientes al estilo de WooCommerce (paso 5.4): lista, ficha con datos fiscales y
 * recargo de equivalencia, direcciones, diseños y solicitudes de borrado. El correo
 * lo cambia el propio cliente (con verificación); el equipo no.
 */
class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Clientes;

    protected static ?string $navigationLabel = 'Clientes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    protected static ?string $slug = 'clientes';

    protected static ?string $recordTitleAttribute = 'nombre';

    /** Solicitudes de borrado pendientes, a la vista en el menú (RGPD: un mes para atenderlas). */
    public static function getNavigationBadge(): ?string
    {
        $pendientes = rescue(fn () => Cliente::whereNotNull('borrado_solicitado_en')->count(), 0, report: false);

        return $pendientes > 0 ? (string) $pendientes : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Solicitudes de borrado pendientes';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Datos del cliente')->columnSpan(2)->columns(2)->schema([
                TextInput::make('nombre')->label('Nombre o razón social')->required()->maxLength(150),
                Placeholder::make('correo_ficha')->label('Correo')->content(fn (?Cliente $record) => $record?->correo)
                    ->helperText('Lo cambia el propio cliente, con verificación.'),
                Select::make('tipo')->label('Tipo')->options(TipoCliente::class)->required()->native(false)->selectablePlaceholder(false)->live(),
                TextInput::make('nif')->label('NIF, NIE o CIF')->maxLength(20)
                    ->dehydrateStateUsing(fn ($state) => blank($state) ? null : Nif::normalizar($state))
                    ->rule(fn () => fn ($a, $v, $fallo) => blank($v) || Nif::valido($v) ? null : $fallo('El NIF, NIE o CIF no es válido.')),
                TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30)->regex(ReglasDireccion::TELEFONO),
                Select::make('idioma')->label('Idioma')->options(config('idiomas.disponibles'))->required()->native(false),
                Toggle::make('recargo_equivalencia')->label('Recargo de equivalencia')->columnSpanFull()
                    ->helperText('Solo autónomos y empresas del comercio minorista. Cambia los impuestos de sus facturas.')
                    ->visible(fn (Get $get) => TipoCliente::tryFrom((string) ($get('tipo') instanceof TipoCliente ? $get('tipo')->value : $get('tipo')))?->admiteRecargo() ?? false),
            ]),
            Section::make('Cuenta')->columnSpan(1)->schema([
                Placeholder::make('cuenta')->label('Acceso')->content(fn (?Cliente $record) => match (true) {
                    $record === null => '—',
                    $record->esInvitado() => 'Invitado (sin cuenta)',
                    $record->usuario?->hasVerifiedEmail() => 'Con cuenta, correo confirmado',
                    default => 'Con cuenta, correo sin confirmar',
                }),
                Toggle::make('activo')->label('Activo')->helperText('Desactivado, no puede entrar en su cuenta ni usar su ficha en la tienda. La cuenta de un empleado que compra se gestiona en Usuarios.'),
                Placeholder::make('marketing')->label('Consentimiento de marketing')->content(fn (?Cliente $record) => $record?->marketing_consentido_en
                    ? 'Sí, desde el '.$record->marketing_consentido_en->format('d/m/Y H:i').' ('.$record->marketing_origen.')' : 'No'),
                Placeholder::make('alta')->label('Alta')->content(fn (?Cliente $record) => $record?->created_at?->format('d/m/Y H:i')),
                Placeholder::make('borrado')->label('Solicitud de borrado')->visible(fn (?Cliente $record) => $record?->borrado_solicitado_en !== null)
                    ->content(fn (?Cliente $record) => 'Pedida el '.$record?->borrado_solicitado_en?->format('d/m/Y H:i').'. Atiéndela en un mes como máximo.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('usuario'))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Todavía no hay clientes')
            ->columns([
                TextColumn::make('nombre')->label('Cliente')->searchable()->sortable()->description(fn (Cliente $r) => $r->correo),
                TextColumn::make('correo')->label('Correo')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tipo')->label('Tipo')->badge(),
                TextColumn::make('nif')->label('NIF')->searchable()->placeholder('—'),
                IconColumn::make('recargo_equivalencia')->label('Recargo')->boolean(),
                TextColumn::make('cuenta')->label('Cuenta')->badge()
                    ->state(fn (Cliente $r) => $r->esInvitado() ? 'Invitado' : ($r->usuario?->hasVerifiedEmail() ? 'Confirmada' : 'Sin confirmar'))
                    ->color(fn (string $state) => match ($state) {
                        'Confirmada' => 'success', 'Sin confirmar' => 'warning', default => 'gray'
                    }),
                TextColumn::make('disenos_count')->label('Diseños')->counts('disenos')->sortable(),
                TextColumn::make('borrado_solicitado_en')->label('Borrado pedido')->dateTime('d/m/Y')->placeholder('—')->color('danger')->sortable(),
                IconColumn::make('activo')->label('Activo')->boolean(),
                TextColumn::make('created_at')->label('Alta')->dateTime('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipo')->label('Tipo')->options(TipoCliente::class),
                TernaryFilter::make('borrado_solicitado_en')->label('Borrado solicitado')->nullable(),
                TernaryFilter::make('recargo_equivalencia')->label('Recargo de equivalencia'),
                TernaryFilter::make('con_cuenta')->label('Con cuenta')
                    ->queries(true: fn (Builder $query) => $query->whereNotNull('user_id'), false: fn (Builder $query) => $query->whereNull('user_id')),
            ])
            ->recordActions([EditAction::make()->label('Ver')]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DireccionesRelationManager::class,
            RelationManagers\DisenosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientes::route('/'),
            'edit' => EditCliente::route('/{record}'),
        ];
    }
}
