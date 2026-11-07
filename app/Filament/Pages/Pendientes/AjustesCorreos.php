<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AjustesCorreos extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'Correos electrónicos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'ajustes-correos';

    protected static string $disponibleEn = 'fase 6';

    protected static string $descripcion = 'Plantillas de correo por evento en español e inglés y configuración SMTP.';

    protected static ?string $equivalenteWooCommerce = 'Ajustes → Correos electrónicos';
}
