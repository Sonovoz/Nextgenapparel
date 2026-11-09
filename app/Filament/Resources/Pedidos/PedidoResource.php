<?php

namespace App\Filament\Resources\Pedidos;

use App\Dominio\Clientes\Paises;
use App\Dominio\Pedidos\CambiarEstado;
use App\Dominio\Pedidos\CambioNoValido;
use App\Dominio\Pedidos\CancelarPorCliente;
use App\Dominio\Pedidos\EstadoPedido;
use App\Dominio\Precios\Formato;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\Pedidos\Pages\ListPedidos;
use App\Filament\Resources\Pedidos\Pages\ViewPedido;
use App\Models\Pedido;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Pedidos como en WooCommerce (paso 6.3): lista con estados y cambio en bloque, y ficha
 * con artículos (maquetas y originales), estado, cliente y entrega, notas e historial.
 * Un pedido no se edita: es la copia de lo que el cliente confirmó (D169).
 */
class PedidoResource extends Resource
{
    protected static ?string $model = Pedido::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Pedidos;

    protected static ?string $navigationLabel = 'Pedidos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'pedido';

    protected static ?string $pluralModelLabel = 'pedidos';

    protected static ?string $slug = 'pedidos';

    protected static ?string $recordTitleAttribute = 'numero';

    /** Pedidos que esperan al equipo: pagados o con el diseño por revisar. */
    public static function getNavigationBadge(): ?string
    {
        $n = rescue(fn () => Pedido::whereIn('estado', [EstadoPedido::Pagado->value, EstadoPedido::DisenoEnRevision->value])
            ->orWhere(fn (Builder $q) => self::conCancelacionPendiente($q))->count(), 0, report: false);

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pagados, con el diseño por revisar o con una petición de cancelación';
    }

    /** Peticiones de cancelación del cliente que el equipo aún no ha resuelto (D177). */
    public static function conCancelacionPendiente(Builder $query): Builder
    {
        return $query->whereNotNull('cancelacion_solicitada_en')
            ->whereIn('estado', array_map(fn (EstadoPedido $e) => $e->value, CancelarPorCliente::CON_PETICION));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Todavía no hay pedidos')
            ->columns([
                TextColumn::make('numero')->label('Pedido')->searchable()->weight('bold')
                    ->description(fn (Pedido $r) => $r->nombre_fiscal),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('correo')->label('Correo')->searchable()->toggleable(),
                TextColumn::make('cliente')->label('Cliente')->badge()
                    ->state(fn (Pedido $r) => match (true) {
                        ! $r->correoConfirmado() => 'Correo sin confirmar',
                        $r->cliente?->user_id !== null => 'Con cuenta',
                        default => 'Invitado',
                    })
                    ->color(fn (string $state) => $state === 'Correo sin confirmar' ? 'warning' : 'gray'),
                TextColumn::make('destino')->label('Destino')->toggleable()
                    ->state(fn (Pedido $r) => trim(($r->direccion_envio['localidad'] ?? '').' · '.($r->direccion_envio['pais'] ?? ''), ' ·')),
                TextColumn::make('total')->label('Total')->alignEnd()->sortable()->formatStateUsing(fn (int $state) => Formato::euros($state, 'es')),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (EstadoPedido $state) => $state->etiqueta())->color(fn (EstadoPedido $state) => $state->color())
                    ->description(fn (Pedido $r) => $r->cancelacion_solicitada_en !== null && in_array($r->estado, CancelarPorCliente::CON_PETICION, true) ? 'Pide cancelar' : null),
                TextColumn::make('fecha_estimada_entrega')->label('Entrega estimada')->date('d/m/Y')->sortable()->placeholder('—'),
                IconColumn::make('maquetas')->label('Maquetas')->boolean()->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn (Pedido $r) => $r->lineas()->whereHas('maquetas')->exists()),
            ])
            ->filters([
                SelectFilter::make('estado')->label('Estado')->multiple()
                    ->options(collect(EstadoPedido::cases())->mapWithKeys(fn (EstadoPedido $e) => [$e->value => $e->etiqueta()])->all()),
                TernaryFilter::make('correo_confirmado_en')->label('Correo confirmado')->nullable(),
                Filter::make('cancelacion')->label('Piden cancelar')->query(fn (Builder $query) => self::conCancelacionPendiente($query)),
            ])
            ->recordActions([ViewAction::make()->label('Ver')])
            ->toolbarActions([
                BulkAction::make('cambiar_estado')->label('Cambiar estado')->icon(Heroicon::OutlinedArrowPath)
                    ->schema([
                        // En bloque no se envía: cada envío pide su transportista y su seguimiento.
                        Select::make('estado')->label('Nuevo estado')->required()->native(false)
                            ->options(collect(EstadoPedido::cases())->reject(fn (EstadoPedido $e) => in_array($e, [EstadoPedido::Enviado, EstadoPedido::PendientePago, EstadoPedido::Reembolsado], true))
                                ->mapWithKeys(fn (EstadoPedido $e) => [$e->value => $e->etiqueta()])->all()),
                        Textarea::make('nota')->label('Nota para el historial')->maxLength(500),
                    ])
                    ->action(function (Collection $pedidos, array $data): void {
                        $hechos = [];
                        $fallos = [];
                        foreach ($pedidos as $pedido) {
                            try {
                                app(CambiarEstado::class)($pedido, EstadoPedido::from($data['estado']), auth()->user(), $data['nota'] ?? null);
                                $hechos[] = $pedido->numero;
                            } catch (CambioNoValido $e) {
                                $fallos[] = $e->getMessage();
                            }
                        }
                        Notification::make()->title(count($hechos).' '.(count($hechos) === 1 ? 'pedido cambiado' : 'pedidos cambiados'))
                            ->body($fallos === [] ? null : implode("\n", $fallos))
                            ->{$fallos === [] ? 'success' : 'warning'}()->persistent(fn () => $fallos !== [])->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $euros = fn (?int $state) => Formato::euros((int) $state, 'es');

        return $schema->columns(3)->components([
            Section::make('Artículos')->columnSpan(2)->schema([
                ViewEntry::make('lineas')->hiddenLabel()->view('filament.pedidos.lineas'),
            ]),
            Section::make('Estado')->columnSpan(1)->schema([
                TextEntry::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (EstadoPedido $state) => $state->etiqueta())->color(fn (EstadoPedido $state) => $state->color())
                    ->helperText(fn (Pedido $r) => 'El cliente ve: «'.$r->estado->visible($r->idioma).'»'),
                TextEntry::make('cancelacion_solicitada_en')->label('El cliente pide cancelar')->dateTime('d/m/Y H:i')->color('danger')->weight('bold')
                    ->visible(fn (Pedido $r) => $r->cancelacion_solicitada_en !== null && in_array($r->estado, CancelarPorCliente::CON_PETICION, true))
                    ->helperText(fn (Pedido $r) => ($r->motivo_cancelacion ? 'Motivo: '.$r->motivo_cancelacion.'. ' : '').'Cancélalo con «Cambiar estado» (se emite la rectificativa) y devuelve el dinero en el TPV hasta que llegue Redsys.'),
                TextEntry::make('created_at')->label('Creado')->dateTime('d/m/Y H:i'),
                TextEntry::make('correo_confirmado_en')->label('Correo confirmado')->dateTime('d/m/Y H:i')->placeholder('Pendiente: el pedido no se puede dar por pagado'),
                TextEntry::make('fecha_estimada_entrega')->label('Entrega estimada')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('seguimiento')->label('Envío')->visible(fn (Pedido $r) => $r->numero_seguimiento !== null)
                    ->state(fn (Pedido $r) => $r->transportista.' · '.$r->numero_seguimiento),
                TextEntry::make('facturas')->label('Facturas')->html()->placeholder('Sin factura')
                    ->state(fn (Pedido $r) => $r->facturas->map(fn ($f) => '<a href="'.e(route('backend.pedidos.factura', ['pedido' => $r->uuid, 'factura' => $f->uuid])).'" target="_blank" style="text-decoration: underline;">'.e($f->codigo).'</a> · '.e($f->tipo->etiqueta('es')).' · '.e(Formato::euros($f->total, 'es')))->join('<br>') ?: null),
                TextEntry::make('cupon')->label('Cupón')->placeholder('—'),
                TextEntry::make('total')->label('Total')->formatStateUsing($euros)->weight('bold')
                    ->helperText(fn (Pedido $r) => 'Base '.$euros($r->base_imponible).' · impuestos '.$euros($r->impuestos).' · envío '.$euros($r->envio)
                        .($r->descuentos > 0 ? ' · descuentos −'.$euros($r->descuentos) : '')),
            ]),
            Section::make('Cliente y entrega')->columnSpan(2)->columns(2)->schema([
                TextEntry::make('nombre_fiscal')->label('Facturar a')
                    ->helperText(fn (Pedido $r) => trim(($r->nif ?? '').($r->recargo_equivalencia ? ' · con recargo de equivalencia' : ''), ' ·') ?: null),
                TextEntry::make('cuenta')->label('Ficha')
                    ->state(fn (Pedido $r) => $r->cliente === null ? 'Sin ficha (correo sin confirmar)' : ($r->cliente->user_id === null ? 'Invitado' : 'Cliente con cuenta'))
                    ->url(fn (Pedido $r) => $r->cliente === null ? null : ClienteResource::getUrl('edit', ['record' => $r->cliente])),
                TextEntry::make('correo')->label('Correo')->copyable(),
                TextEntry::make('telefono')->label('Teléfono')->state(fn (Pedido $r) => $r->direccion_envio['telefono'] ?? null)->copyable(),
                TextEntry::make('direccion')->label('Dirección de envío')->columnSpanFull()
                    ->state(fn (Pedido $r) => implode(', ', array_filter([
                        $r->direccion_envio['nombre'] ?? null, $r->direccion_envio['empresa'] ?? null, $r->direccion_envio['direccion'] ?? null, $r->direccion_envio['direccion_2'] ?? null,
                        trim(($r->direccion_envio['codigo_postal'] ?? '').' '.($r->direccion_envio['localidad'] ?? '')), $r->direccion_envio['provincia'] ?? null,
                        Paises::disponibles()[$r->direccion_envio['pais'] ?? ''] ?? ($r->direccion_envio['pais'] ?? null),
                    ]))),
            ]),
            Section::make('Notas del pedido')->columnSpan(1)->schema([
                ViewEntry::make('notas')->hiddenLabel()->view('filament.pedidos.notas'),
            ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('cliente');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPedidos::route('/'),
            'view' => ViewPedido::route('/{record}'),
        ];
    }
}
