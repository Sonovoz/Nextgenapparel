<?php

namespace App\Filament\Resources\Precios\Pages;

use App\Filament\Resources\Precios\ReglaPrecioResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageReglaPrecio extends ManageRecords
{
    protected static string $resource = ReglaPrecioResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getNavigationLabel());
    }

    protected function getHeaderActions(): array
    {
        return [ReglaPrecioResource::accionCrear()];
    }
}
