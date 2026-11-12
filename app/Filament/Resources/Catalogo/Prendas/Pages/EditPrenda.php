<?php

namespace App\Filament\Resources\Catalogo\Prendas\Pages;

use App\Dominio\Catalogo\Escaparate;
use App\Filament\Concerns\AccionSeoIa;
use App\Filament\Concerns\CamposTraducibles;
use App\Filament\Resources\Catalogo\Prendas\PrendaResource;
use App\Models\Prenda;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
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

    /** Al guardar: ya está en la tienda (no hay caché), con un enlace para verlo. */
    protected function getSavedNotification(): ?Notification
    {
        $prenda = $this->getRecord();
        $url = rescue(fn () => $prenda instanceof Prenda && $prenda->activa && $prenda->familia
            ? route('tienda.prenda', ['familia' => Escaparate::slug($prenda->familia, app()->getLocale()), 'prenda' => Escaparate::slug($prenda, app()->getLocale())])
            : null, null, report: false);

        return Notification::make()->success()->title('Cambios guardados')
            ->body($url ? 'Ya se ven en la tienda.' : 'La prenda no está activa: no se ve en la tienda.')
            ->actions($url ? [Action::make('ver')->label('Ver en la tienda')->url($url, shouldOpenInNewTab: true)] : []);
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
