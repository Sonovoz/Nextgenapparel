<?php

namespace App\Filament\Resources\Precios\Pages;

use App\Dominio\Asistente\AsistenteEnvios;
use App\Dominio\Asistente\IaNoDisponible;
use App\Dominio\Asistente\RedactorSeo;
use App\Dominio\Precios\Formato;
use App\Dominio\Precios\MetodoEnvio;
use App\Dominio\Precios\Motor\CalculoEnvio;
use App\Dominio\Precios\Motor\Destino;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Resources\Precios\ZonaEnvioResource;
use App\Models\Transportista;
use App\Models\ZonaEnvio;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ManageZonaEnvio extends ManageRecords
{
    protected static string $resource = ZonaEnvioResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getNavigationLabel());
    }

    protected function getHeaderActions(): array
    {
        return [ZonaEnvioResource::accionCrear(), self::conIa(), self::probar()];
    }

    /** D225: prueba un envío con el mismo cálculo que el carrito y dice el importe o el motivo exacto. */
    public static function probar(): Action
    {
        return Action::make('probar')->label('Probar envío')->icon(Heroicon::OutlinedTruck)->color('gray')
            ->modalDescription('El mismo cálculo que hace el carrito al cerrar el pedido.')
            ->modalSubmitActionLabel('Calcular')
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('pais')->label('País (código ISO)')->default('ES')->required()->maxLength(2),
                    TextInput::make('cp')->label('Código postal')->required()->maxLength(10),
                    TextInput::make('kilos')->label('Peso del pedido')->numeric()->minValue(0)->suffix('kg')->default(5)->required(),
                    TextInput::make('unidades')->label('Prendas')->integer()->minValue(1)->default(20)->required(),
                ]),
            ])
            ->action(function (array $data): void {
                $calculo = CalculoEnvio::para(new Destino(strtoupper(trim($data['pais'])), (string) $data['cp']), (int) round((float) $data['kilos'] * 1000), (int) $data['unidades'], 0);
                if ($calculo['envio'] === null) {
                    Notification::make()->danger()->title('No se puede enviar')->body(CalculoEnvio::texto((string) $calculo['motivo'], $calculo['zona']))->persistent()->send();

                    return;
                }
                $e = $calculo['envio'];
                Notification::make()->success()->title('Envío: '.Formato::euros($e['importe']).' sin IVA')
                    ->body("Zona «{$e['zona']}» · plazo {$e['plazo_dias']} días".($e['bultos'] ? " · {$e['bultos']} cajas" : ''))->persistent()->send();
            });
    }

    /**
     * D228: «Crear con IA». Paso 1: la tarifa (texto o foto); la IA la pasa a limpio. Paso 2: vista previa con
     * el cálculo real del envío, dudas y avisos; al confirmar se crean las zonas (inactivas si no se marca).
     */
    public static function conIa(): Action
    {
        return Action::make('ia')->label('Crear con IA')->icon(Heroicon::OutlinedSparkles)->color('gray')
            ->visible(fn () => RedactorSeo::disponible())
            ->modalHeading('Crear zonas de envío con IA')->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel('Crear estas zonas')
            ->steps([
                Step::make('Tarifa')->description('La del transportista, tal cual')->schema([
                    Select::make('transportista_id')->label('Transportista')->native(false)->placeholder('Sin asignar')
                        ->options(fn () => Transportista::orderBy('nombre')->pluck('nombre', 'id')->all()),
                    Textarea::make('texto')->label('Tarifa')->rows(10)->maxLength(20000)
                        ->placeholder("Pega aquí la tarifa: del PDF, del correo o de la web.\nPor ejemplo: Península: hasta 1 kg 5,90 €; hasta 5 kg 7,50 €; kilo adicional 0,60 €. Baleares…"),
                    FileUpload::make('imagen')->label('O una foto o captura de la tarifa')->image()->storeFiles(false)
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(5120)
                        ->helperText('PNG, JPG o WebP de hasta 5 MB. Un PDF: copia su texto o haz una captura.'),
                    Textarea::make('indicaciones')->label('Indicaciones para la IA (opcional)')->rows(2)->maxLength(1000)
                        ->placeholder('Ej.: los precios son sin IVA; plazo 24-48 h; solo península y Baleares.'),
                    Hidden::make('propuesta'),
                ])->afterValidation(function (Get $get, Set $set): void {
                    $archivo = collect((array) $get('imagen'))->first(fn ($f) => $f instanceof UploadedFile);
                    if (blank($get('texto')) && $archivo === null) {
                        Notification::make()->warning()->title('Pega la tarifa o sube una foto')->send();

                        throw new Halt;
                    }
                    $imagen = $archivo instanceof UploadedFile ? 'data:'.$archivo->getMimeType().';base64,'.base64_encode((string) file_get_contents($archivo->getRealPath())) : null;
                    try {
                        $propuesta = app(AsistenteEnvios::class)->leer((string) $get('texto'), $imagen, (string) $get('indicaciones'));
                    } catch (IaNoDisponible $e) {
                        Notification::make()->danger()->title('La IA no ha podido leer la tarifa')->body($e->getMessage())->send();

                        throw new Halt;
                    }
                    if ($propuesta['zonas'] === []) {
                        Notification::make()->warning()->title('No he encontrado ninguna zona con precios')
                            ->body(implode(' ', [...$propuesta['preguntas'], ...$propuesta['descartes']]) ?: 'Revisa que la tarifa tenga destinos y precios.')->persistent()->send();

                        throw new Halt;
                    }
                    $set('propuesta', (string) json_encode($propuesta, JSON_UNESCAPED_UNICODE));
                }),
                Step::make('Revisa')->description('Nada se crea hasta que confirmes')->schema([
                    Placeholder::make('vista')->hiddenLabel()
                        ->content(fn (Get $get) => self::vistaPrevia((array) json_decode((string) $get('propuesta'), true), filled($get('transportista_id')) ? (int) $get('transportista_id') : null)),
                    Toggle::make('activar')->label('Activarlas ya')->default(false)
                        ->helperText('Apagado: se crean inactivas para que las revises (y las actives) en la lista.'),
                ]),
            ])
            ->action(function (array $data): void {
                $propuesta = (array) json_decode((string) ($data['propuesta'] ?? ''), true);
                $creadas = AsistenteEnvios::crear($propuesta, filled($data['transportista_id'] ?? null) ? (int) $data['transportista_id'] : null, (bool) ($data['activar'] ?? false));
                Actividad::apuntar('ajustes', "Zonas de envío creadas con IA: {$creadas}");
                Notification::make()->success()->title($creadas === 1 ? 'Zona creada' : "{$creadas} zonas creadas")
                    ->body(($data['activar'] ?? false) ? 'Ya se usan en el carrito.' : 'Están inactivas: revísalas y actívalas.')->send();
            });
    }

    /** @param  array<mixed>  $propuesta */
    public static function vistaPrevia(array $propuesta, ?int $transportistaId): HtmlString
    {
        $kg = fn (int $g) => rtrim(rtrim(number_format($g / 1000, 3, ',', ''), '0'), ',').' kg';
        $html = '';
        $existentes = ZonaEnvio::where('activa', true)->get();
        foreach (AsistenteEnvios::normalizar($propuesta)['zonas'] as $z) {
            $zona = AsistenteEnvios::modelo($z, $transportistaId);
            $donde = implode(', ', $z['paises']).($z['prefijos_cp'] ? ' · CP '.implode(', ', $z['prefijos_cp']) : ' · todo el país');
            if ($z['metodo'] === MetodoEnvio::PorKilo->value) {
                $precios = collect($z['tramos'])->map(fn ($t) => 'hasta '.$kg($t['hasta_gramos']).': '.Formato::euros($t['precio']))->implode(' · ')
                    .($z['precio_kg_extra'] !== null ? ' · kilo extra: '.Formato::euros($z['precio_kg_extra']) : '');
                $ejemplos = collect([1000, 5000, 10000, 20000])->map(function (int $g) use ($zona, $kg) {
                    $r = CalculoEnvio::enZona($zona, $g, 1, 0);

                    return $kg($g).' → '.($r['envio'] ? Formato::euros($r['envio']['importe']) : 'no se puede enviar');
                })->implode(' · ');
            } else {
                $precios = $z['precio_caja'] !== null ? Formato::euros($z['precio_caja']).' por caja' : 'sin precio por caja';
                $ejemplos = collect([20, 60, 120])->map(function (int $u) use ($zona) {
                    $r = CalculoEnvio::enZona($zona, 0, $u, 0);

                    return $u.' prendas → '.($r['envio'] ? Formato::euros($r['envio']['importe']).' ('.$r['envio']['bultos'].' cajas)' : 'no se puede enviar');
                })->implode(' · ');
            }
            $avisos = CalculoEnvio::problemas($zona);
            if ($z['plazo_dias'] === null) {
                $avisos[] = 'La tarifa no dice el plazo: se pone 2 días, cámbialo si hace falta.';
            }
            foreach ($existentes as $e) {
                if (array_intersect($e->paises ?? [], $z['paises']) !== [] && collect($e->prefijos_cp ?? [])->sort()->values()->all() === collect($z['prefijos_cp'])->sort()->values()->all()) {
                    $avisos[] = 'Ya hay una zona activa para ese destino: «'.$e->nombre.'». Desactiva una de las dos.';
                }
            }
            $html .= '<div style="border:1px solid rgba(127,127,127,.3);border-radius:10px;padding:12px 14px;margin-bottom:10px">'
                .'<p style="margin:0 0 4px"><strong>'.e($z['nombre']).'</strong> · '.e($donde).' · '.e($z['metodo'] === MetodoEnvio::PorKilo->value ? 'por peso' : 'por cajas').' · '.e(($z['plazo_dias'] ?? 2).' días').'</p>'
                .'<p style="margin:0 0 4px">'.e($precios).' <span style="opacity:.7">(sin IVA)</span></p>'
                .'<p style="margin:0;opacity:.8">Cálculo real: '.e($ejemplos).'</p>'
                .($avisos !== [] ? '<p style="margin:6px 0 0;color:#d97706">⚠ '.implode('<br>⚠ ', array_map('e', $avisos)).'</p>' : '')
                .'</div>';
        }
        $dudas = [...(array) ($propuesta['preguntas'] ?? []), ...AsistenteEnvios::normalizar($propuesta)['descartes']];
        if ($dudas !== []) {
            $html .= '<div style="border-radius:10px;padding:10px 14px;background:rgba(217,119,6,.1)"><strong>Dudas de la IA</strong><br>'.implode('<br>', array_map(fn ($d) => '• '.e((string) $d), $dudas)).'</div>';
        }

        return new HtmlString($html.'<p style="opacity:.7;margin-top:8px">La IA solo ha copiado los precios de la tarifa; los importes de ejemplo los calcula el motor de la tienda.</p>');
    }
}
