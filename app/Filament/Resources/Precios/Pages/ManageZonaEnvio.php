<?php

namespace App\Filament\Resources\Precios\Pages;

use App\Filament\Resources\Precios\ZonaEnvioResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageZonaEnvio extends ManageRecords
{
    protected static string $resource = ZonaEnvioResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getNavigationLabel());
    }

    protected function getHeaderActions(): array
    {
        return [ZonaEnvioResource::accionCrear()];
    }
}
