<?php

namespace App\Filament\Resources\Precios\Pages;

use App\Filament\Resources\Precios\TransportistaResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageTransportistas extends ManageRecords
{
    protected static string $resource = TransportistaResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getNavigationLabel());
    }

    protected function getHeaderActions(): array
    {
        return [TransportistaResource::accionCrear()];
    }
}
