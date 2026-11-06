<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;

/**
 * Página de inicio del backend (Escritorio de WooCommerce).
 *
 * En la fase 8 mostrará el panel de ventas; de momento, la cuenta del usuario.
 */
class Escritorio extends Dashboard
{
    protected static ?string $title = 'Escritorio';

    protected static ?string $navigationLabel = 'Escritorio';
}
