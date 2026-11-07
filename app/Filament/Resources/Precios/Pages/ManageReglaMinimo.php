<?php

namespace App\Filament\Resources\Precios\Pages;

use App\Filament\Resources\Precios\ReglaMinimoResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageReglaMinimo extends ManageRecords
{
    protected static string $resource = ReglaMinimoResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getNavigationLabel());
    }

    protected function getHeaderActions(): array
    {
        return [ReglaMinimoResource::accionCrear()];
    }
}
