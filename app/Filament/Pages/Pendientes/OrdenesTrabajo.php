<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class OrdenesTrabajo extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Produccion;

    protected static ?string $navigationLabel = 'Órdenes de trabajo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'ordenes-trabajo';

    protected static string $disponibleEn = 'fase 6';

    protected static string $descripcion = 'Órdenes de trabajo de cada pedido con su paquete ZIP de producción: diseños, maquetas y PDF.';
}
