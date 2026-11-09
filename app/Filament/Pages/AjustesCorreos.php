<?php

namespace App\Filament\Pages;

use App\Dominio\Pedidos\CorreosPedido;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Dominio\Sistema\Correo\Smtp;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Ajuste;
use App\Notifications\Sistema\CorreoDePrueba;
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
use Illuminate\Support\Facades\Notification as Notificaciones;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

/**
 * Ajustes → Correos (paso 6.4), como en WooCommerce: el servidor SMTP (portado de Clave de
 * Alma, ADR 0029) y, por cada estado del pedido, su nombre para el cliente, si le avisa por
 * correo y el asunto y el texto en cada idioma. Lo vacío vuelve al texto por defecto.
 */
class AjustesCorreos extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.ajustes-generales';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'Correos electrónicos';

    protected static ?string $title = 'Correos electrónicos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'ajustes-correos';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Ajustes->permiso());
    }

    public function mount(): void
    {
        $smtp = Smtp::guardado();
        $datos = [
            'smtp' => ['servidor' => $smtp['servidor'] ?? config('mail.mailers.smtp.host'), 'puerto' => $smtp['puerto'] ?? config('mail.mailers.smtp.port'),
                'cifrado' => $smtp['cifrado'] ?? 'ssl', 'usuario' => $smtp['usuario'] ?? config('mail.mailers.smtp.username'),
                'remitente' => $smtp['remitente'] ?? config('mail.from.address'), 'nombre_remitente' => $smtp['nombre_remitente'] ?? config('mail.from.name')],
        ];
        foreach (CorreosPedido::ESTADOS as $estado) {
            foreach (config('idiomas.disponibles') as $i => $nombre) {
                $datos['estados'][$estado->value]['nombre'][$i] = $estado->visible($i);
                $datos['estados'][$estado->value]['asunto'][$i] = CorreosPedido::plantilla($estado, 'asunto', $i);
                $datos['estados'][$estado->value]['texto'][$i] = CorreosPedido::plantilla($estado, 'texto', $i);
            }
            $datos['estados'][$estado->value]['activo'] = CorreosPedido::activo($estado);
        }
        $this->form->fill($datos);
    }

    public function form(Schema $schema): Schema
    {
        $idiomas = config('idiomas.disponibles');
        $secciones = [
            Section::make('Servidor de correo (SMTP)')
                ->description('Los da tu proveedor de correo. Con el de OVH: ssl0.ovh.net, puerto 465, SSL/TLS y como usuario la dirección completa. La contraseña se guarda cifrada y no se vuelve a mostrar.')
                ->columns(3)
                ->schema([
                    TextInput::make('smtp.servidor')->label('Servidor SMTP')->maxLength(190),
                    TextInput::make('smtp.puerto')->label('Puerto')->integer()->minValue(1)->maxValue(65535),
                    Select::make('smtp.cifrado')->label('Cifrado')->native(false)->selectablePlaceholder(false)
                        ->options(['ssl' => 'SSL/TLS (normalmente 465)', 'tls' => 'STARTTLS (normalmente 587)']),
                    TextInput::make('smtp.usuario')->label('Usuario')->maxLength(190),
                    TextInput::make('smtp.contrasena')->label('Contraseña del correo')->password()->dehydrated(true)
                        ->placeholder(Smtp::contrasena() !== null ? '•••••••• guardada: déjala vacía para no cambiarla' : ''),
                    TextInput::make('smtp.remitente')->label('Remitente (correo)')->email(),
                    TextInput::make('smtp.nombre_remitente')->label('Nombre del remitente')->maxLength(80),
                ]),
        ];
        foreach (CorreosPedido::ESTADOS as $estado) {
            $campos = [Toggle::make("estados.{$estado->value}.activo")->label('Avisar al cliente por correo')->columnSpanFull()];
            foreach ($idiomas as $i => $nombre) {
                $campos[] = TextInput::make("estados.{$estado->value}.nombre.{$i}")->label("Nombre para el cliente ({$nombre})")->maxLength(60);
            }
            foreach ($idiomas as $i => $nombre) {
                $campos[] = TextInput::make("estados.{$estado->value}.asunto.{$i}")->label("Asunto ({$nombre})")->maxLength(150);
            }
            foreach ($idiomas as $i => $nombre) {
                $campos[] = Textarea::make("estados.{$estado->value}.texto.{$i}")->label("Texto ({$nombre})")->rows(5)->maxLength(2000);
            }
            $secciones[] = Section::make($estado->etiqueta())->columns(2)->collapsed()
                ->description('Huecos: :nombre, :numero, :estado, :total, :fecha_entrega, :transportista, :seguimiento. El correo lleva siempre el botón con el enlace privado al pedido.')
                ->schema($campos);
        }

        return $schema->statePath('data')->components($secciones);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('guardar')->label('Guardar cambios')->action(function (): void {
                $datos = $this->form->getState();
                $cambiosSmtp = Smtp::guardar($datos['smtp'] ?? []);
                if ($cambiosSmtp !== []) {
                    Actividad::apuntar('ajustes', 'Correo SMTP cambiado: '.implode(', ', $cambiosSmtp));
                }

                // Solo se guarda lo que difiere del texto por defecto: así este puede mejorar con las versiones.
                $plantillas = [];
                $nombres = [];
                $activos = [];
                foreach (CorreosPedido::ESTADOS as $estado) {
                    $e = $datos['estados'][$estado->value] ?? [];
                    if (! empty($e['activo'])) {
                        $activos[] = $estado->value;
                    }
                    foreach (array_keys(config('idiomas.disponibles')) as $i) {
                        foreach (['asunto', 'texto'] as $parte) {
                            $valor = trim((string) ($e[$parte][$i] ?? ''));
                            if ($valor !== '' && $valor !== CorreosPedido::porDefecto($estado->value, $parte, $i)) {
                                $plantillas[$estado->value][$parte][$i] = $valor;
                            }
                        }
                        $nombre = trim((string) ($e['nombre'][$i] ?? ''));
                        if ($nombre !== '' && $nombre !== trans('pedidos.estado_'.$estado->value, [], $i)) {
                            $nombres[$estado->value][$i] = $nombre;
                        }
                    }
                }
                $antes = [Ajuste::obtener('correo.estados_activos'), Ajuste::obtener('correo.plantillas'), Ajuste::obtener('pedidos.estados_visibles')];
                Ajuste::guardar('correo.estados_activos', $activos);
                Ajuste::guardar('correo.plantillas', $plantillas);
                Ajuste::guardar('pedidos.estados_visibles', $nombres);
                if ($antes !== [$activos, $plantillas, $nombres]) {
                    Actividad::apuntar('ajustes', 'Correos del pedido cambiados');
                }
                $this->mount();
                Notification::make()->title('Ajustes guardados')->success()->send();
            }),
            Action::make('prueba')->label('Enviar correo de prueba')->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
                ->fillForm(fn () => ['para' => auth()->user()?->email])
                ->schema([TextInput::make('para')->label('Enviar a')->email()->required()])
                ->action(function (array $data): void {
                    try {
                        Notificaciones::route('mail', $data['para'])->notifyNow(new CorreoDePrueba);
                        Notification::make()->title('Correo de prueba enviado a '.$data['para'])->body('Mira la bandeja de entrada y la de spam.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('No se ha podido enviar')->body(Str::limit($e->getMessage(), 300))->danger()->persistent()->send();
                    }
                }),
        ];
    }
}
