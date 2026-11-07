<?php

namespace App\Filament\Pages;

use App\Dominio\Disenos\Calidad;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Ajuste;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Ajustes → General (como en WooCommerce). */
class AjustesGenerales extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.ajustes-generales';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'General';

    protected static ?string $title = 'Ajustes generales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'ajustes-generales';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Ajustes->permiso());
    }

    public function mount(): void
    {
        $this->form->fill([
            'nombre' => Ajuste::obtener('tienda.nombre'),
            'correo_contacto' => Ajuste::obtener('tienda.correo_contacto'),
            'iva_general' => Ajuste::obtener('impuestos.iva_general') / 100,
            ...Calidad::umbrales(),
            'indexar' => (bool) Ajuste::obtener('seo.indexar'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Tienda')->columns(2)->schema([
                TextInput::make('nombre')->label('Nombre de la tienda')->required()->maxLength(100),
                TextInput::make('correo_contacto')->label('Correo de contacto')->email()->maxLength(255),
            ]),
            Section::make('Impuestos')
                ->description('IVA provisional para mostrar los precios con IVA en la tienda. En la fase 3 se sustituirá por las clases de impuesto de Ajustes → Impuestos.')
                ->schema([
                    TextInput::make('iva_general')->label('IVA general')->suffix('%')->required()
                        // Como los importes: coma o punto y hasta 2 decimales, sin redondeos silenciosos.
                        ->rule('regex:/^\d{1,2}([.,]\d{1,2})?$/')
                        ->validationMessages(['regex' => 'Escribe el tipo con hasta 2 decimales, por ejemplo 21 o 10,5.'])
                        ->formatStateUsing(fn ($state) => $state === null ? null : str_replace('.', ',', (string) $state)),
                ]),
            Section::make('Calidad de los diseños')
                ->description('ppp efectivo mínimo de cada nivel, al tamaño al que se imprime. Por debajo del último, el aviso es fuerte y se recomienda no continuar.')
                ->columns(3)
                ->schema([
                    TextInput::make('correcto')->label('Calidad excelente desde')->integer()->minValue(50)->maxValue(1200)->suffix('ppp')->required()->gt('aceptable'),
                    TextInput::make('aceptable')->label('Buena calidad desde')->integer()->minValue(50)->maxValue(1200)->suffix('ppp')->required()->gt('minimo'),
                    TextInput::make('minimo')->label('Calidad justa desde')->integer()->minValue(50)->maxValue(1200)->suffix('ppp')->required(),
                ]),
            Section::make('Buscadores')
                ->description(app()->environment('production')
                    ? 'Mientras esté apagado, la tienda queda fuera de Google y del resto de buscadores. Enciéndelo al abrir la tienda al público.'
                    : 'Este entorno no es el de producción: la tienda nunca se indexa aquí, aunque lo enciendas.')
                ->schema([
                    Toggle::make('indexar')->label('Permitir que los buscadores indexen la tienda'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('guardar')->label('Guardar cambios')->action(function (): void {
                $datos = $this->form->getState();
                $ivaAnterior = Ajuste::obtener('impuestos.iva_general');
                $ivaNuevo = (int) round(((float) str_replace(',', '.', (string) $datos['iva_general'])) * 100);

                Ajuste::guardar('tienda.nombre', $datos['nombre']);
                Ajuste::guardar('tienda.correo_contacto', $datos['correo_contacto']);
                Ajuste::guardar('impuestos.iva_general', $ivaNuevo);

                $indexarAnterior = (bool) Ajuste::obtener('seo.indexar');
                Ajuste::guardar('seo.indexar', (bool) $datos['indexar']);
                if ($indexarAnterior !== (bool) $datos['indexar']) {
                    Actividad::apuntar('ajustes', $datos['indexar'] ? 'Indexación en buscadores activada' : 'Indexación en buscadores desactivada');
                }

                $umbralesAnteriores = Calidad::umbrales();
                $umbralesNuevos = ['correcto' => (int) $datos['correcto'], 'aceptable' => (int) $datos['aceptable'], 'minimo' => (int) $datos['minimo']];
                foreach ($umbralesNuevos as $nivel => $ppp) {
                    Ajuste::guardar('disenos.ppp_'.$nivel, $ppp);
                }

                if ($umbralesAnteriores !== $umbralesNuevos) {
                    Actividad::apuntar('ajustes', 'Umbrales de calidad cambiados', propiedades: ['old' => $umbralesAnteriores, 'attributes' => $umbralesNuevos]);
                }

                if ($ivaAnterior !== $ivaNuevo) {
                    Actividad::apuntar('ajustes', 'IVA general cambiado', propiedades: ['old' => ['iva' => $ivaAnterior / 100 .' %'], 'attributes' => ['iva' => $ivaNuevo / 100 .' %']]);
                }

                Notification::make()->title('Ajustes guardados')->success()->send();
            }),
        ];
    }
}
