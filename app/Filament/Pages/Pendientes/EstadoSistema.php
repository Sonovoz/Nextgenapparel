<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class EstadoSistema extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Estado del sistema';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'estado-sistema';

    protected static string $disponibleEn = 'fase 1 · paso 1.5';

    protected static string $descripcion = 'Comprobación de salud de la base de datos, las colas, el correo, el disco y las copias, con alertas por correo.';

    protected static ?string $equivalenteWooCommerce = 'Herramientas → Estado';
}
