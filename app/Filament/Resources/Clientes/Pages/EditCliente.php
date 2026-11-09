<?php

namespace App\Filament\Resources\Clientes\Pages;

use App\Dominio\Clientes\BorrarCliente;
use App\Dominio\Clientes\ClienteConPedidos;
use App\Dominio\Clientes\TipoCliente;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Resources\Clientes\ClienteResource;
use App\Models\Cliente;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Ficha del cliente (paso 5.4). Cada cambio del equipo queda en el registro de actividad
 * con el antes y el después. Desactivar al cliente desactiva también su cuenta.
 */
class EditCliente extends EditRecord
{
    protected static string $resource = ClienteResource::class;

    /** @var array<string, mixed> */
    private array $antes = [];

    public function getTitle(): string
    {
        return $this->getRecord()->nombre;
    }

    protected function beforeSave(): void
    {
        $this->antes = $this->getRecord()->getOriginal();
    }

    /** Un particular no tiene recargo: se retira en el mismo guardado, para que conste en el mismo apunte (revisión N3 del 5.4, A1 y M2). */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $tipo = $data['tipo'] instanceof TipoCliente ? $data['tipo'] : TipoCliente::tryFrom((string) ($data['tipo'] ?? ''));
        if ($tipo !== null && ! $tipo->admiteRecargo()) {
            $data['recargo_equivalencia'] = false;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Cliente $cliente */
        $cliente = $this->getRecord();
        $campos = ['nombre', 'tipo', 'nif', 'telefono', 'idioma', 'recargo_equivalencia', 'activo'];
        $cambios = collect($campos)->filter(fn ($c) => $cliente->wasChanged($c));
        if ($cambios->isNotEmpty()) {
            $valor = fn ($v) => $v instanceof \BackedEnum ? $v->value : $v;
            Actividad::apuntar('clientes', 'Cliente modificado', $cliente, [
                'old' => $cambios->mapWithKeys(fn ($c) => [$c => $valor($this->antes[$c] ?? null)])->all(),
                'attributes' => $cambios->mapWithKeys(fn ($c) => [$c => $valor($cliente->{$c})])->all(),
            ]);
        }
        // La cuenta sigue el estado del cliente (salvo la de un empleado, que se gestiona en Usuarios).
        if ($cliente->wasChanged('activo') && $cliente->usuario !== null && ! $cliente->usuario->roles()->exists()) {
            $cliente->usuario->forceFill(['activo' => $cliente->activo])->saveQuietly();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('anular_borrado')->label('Anular la solicitud de borrado')->color('gray')->icon(Heroicon::OutlinedArrowUturnLeft)
                ->visible(fn () => $this->getRecord()->borrado_solicitado_en !== null)
                ->requiresConfirmation()->modalDescription('Úsalo si el cliente ha cambiado de idea. Queda en el registro de actividad.')
                ->action(function (): void {
                    $this->getRecord()->update(['borrado_solicitado_en' => null]);
                    Actividad::apuntar('clientes', 'Solicitud de borrado anulada', $this->getRecord());
                    Notification::make()->title('Solicitud anulada')->success()->send();
                    $this->refreshFormData(['borrado_solicitado_en']);
                }),
            Action::make('borrar')->label('Borrar el cliente')->color('danger')->icon(Heroicon::OutlinedTrash)
                ->requiresConfirmation()
                ->modalHeading('Borrar el cliente y todos sus datos')
                ->modalDescription('Se borran su cuenta, su ficha, sus direcciones y sus diseños (con los archivos). No se puede deshacer.')
                ->schema([
                    TextInput::make('confirmacion')->label('Escribe BORRAR para confirmar')->required()->in(['BORRAR'])
                        ->validationMessages(['in' => 'Escribe BORRAR en mayúsculas.']),
                ])
                ->action(function (BorrarCliente $borrar): void {
                    try {
                        $borrar($this->getRecord(), auth()->user());
                    } catch (ClienteConPedidos) {
                        Notification::make()->title('No se puede borrar: tiene pedidos')
                            ->body('Sus pedidos guardan sus datos y la ley obliga a conservar parte de ellos. Se anonimizará cuando el asesor fije el criterio; mientras, desactívalo si lo necesitas.')
                            ->danger()->persistent()->send();

                        return;
                    }
                    Notification::make()->title('Cliente borrado')->success()->send();
                    $this->redirect(ClienteResource::getUrl('index'));
                }),
        ];
    }
}
