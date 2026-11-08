<?php

namespace App\Filament\Resources\Precios\Pages;

use App\Filament\Resources\Precios\CuponResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageCupon extends ManageRecords
{
    protected static string $resource = CuponResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getNavigationLabel());
    }

    protected function getHeaderActions(): array
    {
        return [CuponResource::accionCrear()];
    }
}
