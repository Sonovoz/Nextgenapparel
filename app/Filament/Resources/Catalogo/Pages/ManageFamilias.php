<?php

namespace App\Filament\Resources\Catalogo\Pages;

use App\Filament\Resources\Catalogo\FamiliaResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageFamilias extends ManageRecords
{
    protected static string $resource = FamiliaResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getPluralModelLabel());
    }

    protected function getHeaderActions(): array
    {
        return [FamiliaResource::accionCrear()];
    }
}
