<?php

namespace App\Filament\Resources\Incidencias\Pages;

use App\Dominio\Facturacion\EmitirFactura;
use App\Dominio\Incidencias\IncidenciaNoValida;
use App\Dominio\Incidencias\ResolucionIncidencia;
use App\Dominio\Incidencias\ResolverIncidencia;
use App\Dominio\Precios\Formato;
use App\Filament\Resources\Incidencias\IncidenciaResource;
use App\Models\Incidencia;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/** Ficha de la incidencia con «Resolver» (paso 6.6b, D178). */
class ViewIncidencia extends ViewRecord
{
    protected static string $resource = IncidenciaResource::class;

    public function getTitle(): string
    {
        /** @var Incidencia $incidencia */
        $incidencia = $this->getRecord();

        return 'Incidencia '.$incidencia->codigo;
    }

    protected function getHeaderActions(): array
    {
        /** @var Incidencia $incidencia */
        $incidencia = $this->getRecord();
        $maximo = app(EmitirFactura::class)->pendienteDeRectificar($incidencia->pedido);

        return [
            Action::make('resolver')->label('Resolver')->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn () => auth()->user()?->can('resolver', $incidencia) ?? false)
                ->modalDescription('Se resuelve una sola vez. El cliente recibe la respuesta por correo y la ve en su pedido.')
                ->schema([
                    Radio::make('resolucion')->label('Resolución')->required()->live()
                        ->options(collect(ResolucionIncidencia::cases())->mapWithKeys(fn (ResolucionIncidencia $r) => [$r->value => $r->etiqueta('es')])->all())
                        ->disableOptionWhen(fn (string $value) => $value === ResolucionIncidencia::Reembolso->value && $maximo === 0),
                    TextInput::make('importe')->label('Importe a devolver, con IVA')->suffix('€')->placeholder('20,50')
                        ->regex('/^\d{1,6}([.,]\d{1,2})?$/')
                        ->rule(fn () => function (string $atributo, mixed $valor, \Closure $falla) use ($maximo): void {
                            $centimos = self::centimos((string) $valor);
                            if ($centimos <= 0 || $centimos > $maximo) {
                                $falla('Tiene que ser mayor que 0 y como mucho '.Formato::euros($maximo, 'es').'.');
                            }
                        })
                        ->visible(fn (Get $get) => $get('resolucion') === ResolucionIncidencia::Reembolso->value)
                        ->required(fn (Get $get) => $get('resolucion') === ResolucionIncidencia::Reembolso->value)
                        ->helperText('Como mucho '.Formato::euros($maximo, 'es').'. Se devuelve por la pasarela y se emite la rectificativa parcial.'),
                    Textarea::make('respuesta')->label('Respuesta para el cliente')->required()->maxLength(2000)->rows(4),
                ])
                ->action(function (array $data) use ($incidencia): void {
                    // Euros del formulario a céntimos sin pasar por coma flotante (regla 7).
                    $importe = isset($data['importe']) ? self::centimos((string) $data['importe']) : null;
                    try {
                        app(ResolverIncidencia::class)($incidencia, ResolucionIncidencia::from($data['resolucion']), (string) $data['respuesta'], $importe, auth()->user());
                        Notification::make()->title('Incidencia resuelta')->success()->send();
                        // La ficha vuelve a cargarse: estado, respuesta y rectificativa, y sin «Resolver».
                        $this->redirect(IncidenciaResource::getUrl('view', ['record' => $incidencia]));
                    } catch (IncidenciaNoValida $e) {
                        Notification::make()->title('No se ha resuelto')->body($e->motivo)->danger()->send();
                    }
                }),
        ];
    }

    /** «20», «20,5» o «20.50» → 2050. */
    public static function centimos(string $euros): int
    {
        $euros = str_replace(',', '.', trim($euros));
        [$enteros, $decimales] = array_pad(explode('.', $euros, 2), 2, '0');

        return (int) $enteros * 100 + (int) str_pad(substr($decimales, 0, 2), 2, '0');
    }
}
