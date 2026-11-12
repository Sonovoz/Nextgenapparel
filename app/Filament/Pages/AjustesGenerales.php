<?php

namespace App\Filament\Pages;

use App\Dominio\Carrito\CarritoActual;
use App\Dominio\Catalogo\OptimizarFoto;
use App\Dominio\Disenos\AnalizadorPng;
use App\Dominio\Disenos\Calidad;
use App\Dominio\Disenos\MejoraIa;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Ajuste;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
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
            'foto_max_px' => OptimizarFoto::limites()['max_px'],
            'foto_calidad' => OptimizarFoto::limites()['calidad'],
            'recargo_lo_indica' => Ajuste::obtener('clientes.recargo_lo_indica'),
            'carrito_dias' => CarritoActual::dias(),
            'subidas_hora' => (int) Ajuste::obtener('disenos.subidas_hora'),
            'max_dia' => (int) Ajuste::obtener('disenos.max_dia'),
            'indexar' => (bool) Ajuste::obtener('seo.indexar'),
            'pedidos_prefijo' => Ajuste::obtener('pedidos.prefijo'),
            'pedidos_dias_confirmar' => Ajuste::obtener('pedidos.dias_confirmar_correo'),
            'pedidos_dias_sin_pago' => Ajuste::obtener('pedidos.dias_sin_pago'),
            'incidencias_dias' => Ajuste::obtener('incidencias.dias'),
            'presupuestos_dias' => Ajuste::obtener('presupuestos.dias_validez'),
            'cancelacion_automatica' => (bool) Ajuste::obtener('pagos.cancelacion_automatica'),
            'pedidos_tramos' => (array) Ajuste::obtener('pedidos.plazo_tramos'),
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
            Section::make('Fotos del catálogo')
                ->description('Al subir una foto de producto se pasa a WebP y se reduce a este lado mayor, para que la tienda cargue rápido. Las zonas de impresión no se mueven. Las que ya estaban se optimizan en la próxima hora.')
                ->columns(2)
                ->schema([
                    TextInput::make('foto_max_px')->label('Lado mayor, como mucho')->integer()->minValue(400)->maxValue(6000)->suffix('px')->required()
                        ->helperText('2000 px se ven nítidas en pantallas grandes.'),
                    TextInput::make('foto_calidad')->label('Calidad WebP')->integer()->minValue(40)->maxValue(100)->required()
                        ->helperText('82 apenas se distingue del original y pesa mucho menos.'),
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
                    Toggle::make('exigir_ppp')->label('Exigir que el PNG indique su resolución (ppp)')->inline(false)
                        ->helperText('Si el archivo no la declara, no se admite (D206).'),
                    TextInput::make('dias_sin_pedido')->label('Guardar sin pedido')->integer()->minValue(1)->maxValue(365)->suffix('días')->required(),
                    TextInput::make('subidas_hora')->label('Subidas por hora y conexión')->integer()->minValue(5)->maxValue(1000)->required()
                        ->helperText('Frena el abuso; quien prueba varias versiones de su logo no llega.'),
                    TextInput::make('max_dia')->label('Diseños nuevos por visitante y día')->integer()->minValue(5)->maxValue(1000)->required(),
                ]),
            Section::make('Clientes')
                ->description('El recargo de equivalencia solo es de autónomos y empresas del comercio minorista y cambia los impuestos de sus facturas. Consulta con tu asesor quién debe indicarlo. El equipo siempre puede cambiarlo en la ficha del cliente. Si pasas de «el propio cliente» a «el equipo», los recargos que marcaron los clientes siguen activos: revísalos en Clientes con el filtro «Recargo de equivalencia».')
                ->schema([
                    Select::make('recargo_lo_indica')->label('El recargo de equivalencia lo indica')->required()->selectablePlaceholder(false)->options([
                        'equipo' => 'El equipo, en la ficha del cliente (el cliente solo lo ve)',
                        'cliente' => 'El propio cliente, en «Mis datos»',
                    ]),
                ]),
            Section::make('Carrito')
                ->description('Lo que el cliente deja en el carrito, con cuenta o sin ella, se guarda este tiempo desde la última vez que lo usa. Después se borra. Los diseños de visitantes sin cuenta que solo estaban en ese carrito se borran cuando además superan los días de «Guardar sin pedido».')
                ->schema([
                    TextInput::make('carrito_dias')->label('Guardar el carrito')->integer()->minValue(1)->maxValue(365)->suffix('días')->required(),
                ]),
            Section::make('Pedidos')
                ->description('Número de los pedidos, plazos para confirmar el correo y para pagar, y días extra de producción según las unidades (se suman al plazo de la familia y del transportista, en días laborables).')
                ->columns(3)
                ->schema([
                    TextInput::make('pedidos_prefijo')->label('Prefijo del número')->required()->maxLength(10)->regex('/^[A-Z0-9-]+$/')->helperText('PED- da PED-0001.'),
                    TextInput::make('pedidos_dias_confirmar')->label('Días del enlace para confirmar el correo')->integer()->minValue(1)->maxValue(60)->required(),
                    TextInput::make('pedidos_dias_sin_pago')->label('Cancelar sin pagar a los')->integer()->minValue(1)->maxValue(60)->suffix('días')->required()
                        ->helperText('Se cancela solo y devuelve el uso del cupón.'),
                    TextInput::make('incidencias_dias')->label('Plazo para abrir una incidencia')->integer()->minValue(1)->maxValue(90)->suffix('días')->required()
                        ->helperText('Desde la entrega (o desde el envío si no se marcó).'),
                    TextInput::make('presupuestos_dias')->label('Validez de los presupuestos')->integer()->minValue(1)->maxValue(90)->suffix('días')->required(),
                    Toggle::make('cancelacion_automatica')->label('El cliente cancela un pedido pagado al momento')->columnSpanFull()
                        ->helperText('Si está pagado y aún no está en producción, se cancela y se le devuelve el dinero por Redsys. Desactivado: queda una petición para el equipo.'),
                    Repeater::make('pedidos_tramos')->label('Días extra según las unidades')->columnSpanFull()->columns(2)->defaultItems(0)
                        ->addActionLabel('Añadir tramo')->reorderable(false)
                        ->schema([
                            TextInput::make('desde')->label('Desde (unidades)')->integer()->minValue(1)->required(),
                            TextInput::make('dias')->label('Días extra')->integer()->minValue(0)->maxValue(60)->required(),
                        ]),
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

                $fotosAntes = OptimizarFoto::limites();
                Ajuste::guardar('catalogo.foto_max_px', (int) $datos['foto_max_px']);
                Ajuste::guardar('catalogo.foto_calidad', (int) $datos['foto_calidad']);
                if ($fotosAntes !== OptimizarFoto::limites()) {
                    Actividad::apuntar('ajustes', 'Tamaño y calidad de las fotos del catálogo cambiados', propiedades: ['old' => $fotosAntes, 'attributes' => OptimizarFoto::limites()]);
                }

                $recargoAntes = Ajuste::obtener('clientes.recargo_lo_indica');
                Ajuste::guardar('clientes.recargo_lo_indica', $datos['recargo_lo_indica'] === 'cliente' ? 'cliente' : 'equipo');
                if ($recargoAntes !== Ajuste::obtener('clientes.recargo_lo_indica')) {
                    Actividad::apuntar('ajustes', 'Quién indica el recargo de equivalencia cambiado', propiedades: ['old' => ['recargo_lo_indica' => $recargoAntes], 'attributes' => ['recargo_lo_indica' => Ajuste::obtener('clientes.recargo_lo_indica')]]);
                }

                $carritoAntes = CarritoActual::dias();
                Ajuste::guardar('carrito.dias', (int) $datos['carrito_dias']);
                if ($carritoAntes !== CarritoActual::dias()) {
                    Actividad::apuntar('ajustes', 'Días que se guarda el carrito cambiados', propiedades: ['old' => ['carrito_dias' => $carritoAntes], 'attributes' => ['carrito_dias' => CarritoActual::dias()]]);
                }

                Ajuste::guardar('pedidos.prefijo', $datos['pedidos_prefijo']);
                Ajuste::guardar('pedidos.dias_confirmar_correo', (int) $datos['pedidos_dias_confirmar']);
                Ajuste::guardar('pedidos.dias_sin_pago', (int) $datos['pedidos_dias_sin_pago']);
                Ajuste::guardar('incidencias.dias', (int) $datos['incidencias_dias']);
                Ajuste::guardar('presupuestos.dias_validez', (int) $datos['presupuestos_dias']);
                Ajuste::guardar('pagos.cancelacion_automatica', (bool) $datos['cancelacion_automatica']);
                Ajuste::guardar('pedidos.plazo_tramos', collect($datos['pedidos_tramos'] ?? [])
                    ->map(fn ($t) => ['desde' => (int) $t['desde'], 'dias' => (int) $t['dias']])->sortBy('desde')->values()->all());

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
