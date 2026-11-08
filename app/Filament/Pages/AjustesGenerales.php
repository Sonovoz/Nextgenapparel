<?php

namespace App\Filament\Pages;

use App\Dominio\Disenos\AnalizadorPng;
use App\Dominio\Disenos\Calidad;
use App\Dominio\Disenos\MejoraIa;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Ajuste;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
            ...Calidad::umbrales(),
            ...AnalizadorPng::limites(),
            'dias_sin_pedido' => (int) Ajuste::obtener('disenos.dias_sin_pedido'),
            'subidas_hora' => (int) Ajuste::obtener('disenos.subidas_hora'),
            'max_dia' => (int) Ajuste::obtener('disenos.max_dia'),
            'indexar' => (bool) Ajuste::obtener('seo.indexar'),
            'ia_activa' => MejoraIa::activa(),
            'ia_desde' => MejoraIa::desde(),
            'ia_texto' => collect(config('idiomas.disponibles'))->keys()->mapWithKeys(fn ($i) => [$i => MejoraIa::texto($i)])->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Tienda')->columns(2)->schema([
                TextInput::make('nombre')->label('Nombre de la tienda')->required()->maxLength(100),
                TextInput::make('correo_contacto')->label('Correo de contacto')->email()->maxLength(255),
            ]),
            Section::make('Calidad de los diseños')
                ->description('ppp efectivo mínimo de cada nivel, al tamaño al que se imprime. Por debajo del último, el aviso es fuerte y se recomienda no continuar.')
                ->columns(3)
                ->schema([
                    TextInput::make('correcto')->label('Calidad excelente desde')->integer()->minValue(50)->maxValue(1200)->suffix('ppp')->required()->gt('aceptable'),
                    TextInput::make('aceptable')->label('Buena calidad desde')->integer()->minValue(50)->maxValue(1200)->suffix('ppp')->required()->gt('minimo'),
                    TextInput::make('minimo')->label('Calidad justa desde')->integer()->minValue(50)->maxValue(1200)->suffix('ppp')->required(),
                ]),
            Section::make('Archivos de diseño')
                ->description('Límites de los PNG que suben los clientes. El mínimo se mide sin los márgenes transparentes, sobre lo que se imprime. Los diseños que no llegan a un pedido se borran pasados los días indicados.')
                ->columns(4)
                ->schema([
                    TextInput::make('max_mb')->label('Peso máximo')->integer()->minValue(1)->maxValue(50)->suffix('MB')->required()
                        ->helperText(fn () => 'Este servidor admite subidas de hasta '.intdiv(min(AnalizadorPng::bytesIni((string) ini_get('upload_max_filesize')), AnalizadorPng::bytesIni((string) ini_get('post_max_size'))), 1024 * 1024).' MB (php.ini).'),
                    TextInput::make('max_px')->label('Lado mayor, como mucho')->integer()->minValue(1000)->maxValue(10000)->suffix('px')->required()
                        ->helperText('8000 px son 67 cm a 300 ppp. Más solo hace la subida más lenta.'),
                    TextInput::make('min_px')->label('Lado menor, al menos')->integer()->minValue(50)->maxValue(2000)->suffix('px')->required(),
                    TextInput::make('dias_sin_pedido')->label('Guardar sin pedido')->integer()->minValue(1)->maxValue(365)->suffix('días')->required(),
                    TextInput::make('subidas_hora')->label('Subidas por hora y conexión')->integer()->minValue(5)->maxValue(1000)->required()
                        ->helperText('Frena el abuso; quien prueba varias versiones de su logo no llega.'),
                    TextInput::make('max_dia')->label('Diseños nuevos por visitante y día')->integer()->minValue(5)->maxValue(1000)->required(),
                ]),
            Section::make('Mejorar el diseño con IA')
                ->description('Si la calidad no llega, el cliente ve un botón con estas instrucciones para mejorar su diseño con su propia herramienta de IA y volver a subirlo. La tienda no envía nada a ninguna IA. Escribe :medida donde va el tamaño de impresión (por ejemplo, «28 × 28 cm»). Si vacías un texto, se usa el de por defecto.')
                ->columns(2)
                ->schema([
                    Toggle::make('ia_activa')->label('Ofrecer las instrucciones al cliente'),
                    Select::make('ia_desde')->label('Ofrecerlas cuando la calidad sea')->required()->selectablePlaceholder(false)->options([
                        'buena' => 'Buena o peor (por debajo de excelente)',
                        'justa' => 'Justa o baja',
                        'baja' => 'Solo baja',
                    ]),
                    ...collect(config('idiomas.disponibles'))->map(fn ($nombre, $i) => Textarea::make("ia_texto.{$i}")->label("Instrucciones ({$nombre})")->rows(14)->maxLength(5000)->columnSpanFull())->values()->all(),
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
                Ajuste::guardar('tienda.nombre', $datos['nombre']);
                Ajuste::guardar('tienda.correo_contacto', $datos['correo_contacto']);

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

                $archivosAnteriores = [...AnalizadorPng::limites(), ...collect(['dias_sin_pedido', 'subidas_hora', 'max_dia'])->mapWithKeys(fn ($c) => [$c => (int) Ajuste::obtener('disenos.'.$c)])->all()];
                $archivosNuevos = [];
                foreach (array_keys($archivosAnteriores) as $clave) {
                    $archivosNuevos[$clave] = (int) $datos[$clave];
                    Ajuste::guardar('disenos.'.$clave, $archivosNuevos[$clave]);
                }
                if ($archivosAnteriores !== $archivosNuevos) {
                    Actividad::apuntar('ajustes', 'Límites de los archivos de diseño cambiados', propiedades: ['old' => $archivosAnteriores, 'attributes' => $archivosNuevos]);
                }

                // Mejora con IA: solo se guarda el texto que difiere del de por defecto, para que este pueda mejorar con las versiones.
                Ajuste::guardar('disenos.ia_activa', (bool) $datos['ia_activa']);
                Ajuste::guardar('disenos.ia_desde', in_array($datos['ia_desde'], MejoraIa::NIVELES, true) ? $datos['ia_desde'] : 'justa');
                $textos = collect($datos['ia_texto'] ?? [])->map(fn ($t, $i) => trim((string) $t) === '' || trim((string) $t) === MejoraIa::porDefecto($i) ? null : trim((string) $t))->filter()->all();
                Ajuste::guardar('disenos.ia_texto', $textos ?: null);

                Notification::make()->title('Ajustes guardados')->success()->send();
            }),
        ];
    }
}
