<?php

namespace App\Filament\Resources\Catalogo\Pages;

use App\Filament\Resources\Catalogo\TallaResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageTallas extends ManageRecords
{
    protected static string $resource = TallaResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getPluralModelLabel());
    }

    protected function getHeaderActions(): array
    {
        return [TallaResource::accionCrear()];
    }
}
