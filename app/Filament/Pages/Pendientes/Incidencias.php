<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Incidencias extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Pedidos;

    protected static ?string $navigationLabel = 'Incidencias';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'incidencias';

    protected static string $disponibleEn = 'fase 6';

    protected static string $descripcion = 'Incidencias abiertas por los clientes con fotos, y su resolución: reimpresión, reembolso parcial o rechazo.';

    protected static ?string $equivalenteWooCommerce = 'plugins de devoluciones';
}
