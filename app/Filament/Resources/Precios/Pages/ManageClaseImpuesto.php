<?php

namespace App\Filament\Resources\Precios\Pages;

use App\Filament\Resources\Precios\ClaseImpuestoResource;
use App\Models\ClaseImpuesto;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageClaseImpuesto extends ManageRecords
{
    protected static string $resource = ClaseImpuestoResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getNavigationLabel());
    }

    protected function getHeaderActions(): array
    {
        return [ClaseImpuestoResource::accionCrear()->after(fn (ClaseImpuesto $record) => ClaseImpuestoResource::unicaPorDefecto($record))];
    }
}
