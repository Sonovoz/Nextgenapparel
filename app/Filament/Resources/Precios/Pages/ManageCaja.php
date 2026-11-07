<?php

namespace App\Filament\Resources\Precios\Pages;

use App\Filament\Resources\Precios\CajaResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageCaja extends ManageRecords
{
    protected static string $resource = CajaResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getNavigationLabel());
    }

    protected function getHeaderActions(): array
    {
        return [CajaResource::accionCrear()];
    }
}
