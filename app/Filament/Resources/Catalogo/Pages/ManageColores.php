<?php

namespace App\Filament\Resources\Catalogo\Pages;

use App\Filament\Resources\Catalogo\ColorResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageColores extends ManageRecords
{
    protected static string $resource = ColorResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getPluralModelLabel());
    }

    protected function getHeaderActions(): array
    {
        return [ColorResource::accionCrear()];
    }
}
