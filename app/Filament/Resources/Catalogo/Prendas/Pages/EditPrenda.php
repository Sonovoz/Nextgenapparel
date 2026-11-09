<?php

namespace App\Filament\Resources\Catalogo\Prendas\Pages;

use App\Filament\Concerns\AccionSeoIa;
use App\Filament\Concerns\CamposTraducibles;
use App\Filament\Resources\Catalogo\Prendas\PrendaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPrenda extends EditRecord
{
    protected static string $resource = PrendaResource::class;

    public function getTitle(): string
    {
        return 'Editar prenda';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return CamposTraducibles::rellenar($data, $this->getRecord());
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CamposTraducibles::limpiar($data, $this->getRecord()->translatable);
    }

    protected function getHeaderActions(): array
    {
        return [
            AccionSeoIa::make('una prenda', ['nombre', 'descripcion', 'composicion', 'detalles'], ['nombre', 'descripcion', 'composicion', 'detalles']),
            DeleteAction::make()->label('Mover a la papelera'),
            RestoreAction::make(),
        ];
    }
}
