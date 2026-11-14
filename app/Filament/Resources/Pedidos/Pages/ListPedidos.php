<?php

namespace App\Filament\Resources\Pedidos\Pages;

use App\Dominio\Pedidos\EstadoPedido as E;
use App\Filament\Resources\Pedidos\PedidoResource;
use App\Models\Pedido;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * D240: tres pestañas, la primera los pagados (lo que hay que producir y enviar). Pendientes de pago y cancelados,
 * aparte, para no mezclarlos.
 */
class ListPedidos extends ListRecords
{
    protected static string $resource = PedidoResource::class;

    /** Estados de cada pestaña. */
    public const PESTANAS = [
        'pagados' => [E::Pagado, E::DisenoEnRevision, E::RequiereCambios, E::EnProduccion, E::Enviado, E::Entregado],
        'pendientes' => [E::PendientePago],
        'cancelados' => [E::Cancelado, E::Reembolsado],
    ];

    public function getTabs(): array
    {
        $contar = fn (string $clave) => rescue(fn () => Pedido::whereIn('estado', self::valores($clave))->count(), 0, report: false);

        return [
            'pagados' => Tab::make('Pagados')->icon('heroicon-o-check-badge')
                ->badge($contar('pagados'))->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('estado', self::valores('pagados'))),
            'pendientes' => Tab::make('Pendientes de pago')->icon('heroicon-o-clock')
                ->badge($contar('pendientes'))->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('estado', self::valores('pendientes'))),
            'cancelados' => Tab::make('Cancelados')->icon('heroicon-o-x-circle')
                ->badge($contar('cancelados'))->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('estado', self::valores('cancelados'))),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'pagados';
    }

    /** @return list<string> */
    public static function valores(string $pestana): array
    {
        return array_map(fn (E $e) => $e->value, self::PESTANAS[$pestana]);
    }
}
