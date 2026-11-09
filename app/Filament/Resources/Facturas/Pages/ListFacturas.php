<?php

namespace App\Filament\Resources\Facturas\Pages;

use App\Filament\Resources\Facturas\FacturaResource;
use Filament\Resources\Pages\ListRecords;

class ListFacturas extends ListRecords
{
    protected static string $resource = FacturaResource::class;
}
