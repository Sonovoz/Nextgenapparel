<?php

namespace App\Filament\Resources\Precios\Pages;

use App\Filament\Resources\Precios\PackResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePacks extends ManageRecords
{
    protected static string $resource = PackResource::class;

    public function getTitle(): string
    {
        return 'Packs';
    }

    protected function getHeaderActions(): array
    {
        return [PackResource::accionCrear()->slideOver()];
    }
}
