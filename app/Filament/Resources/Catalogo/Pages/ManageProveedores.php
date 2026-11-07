<?php

namespace App\Filament\Resources\Catalogo\Pages;

use App\Filament\Resources\Catalogo\ProveedorResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageProveedores extends ManageRecords
{
    protected static string $resource = ProveedorResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getPluralModelLabel());
    }

    protected function getHeaderActions(): array
    {
        return [ProveedorResource::accionCrear()];
    }
}
