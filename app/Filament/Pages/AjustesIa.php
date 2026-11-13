<?php

namespace App\Filament\Pages;

use App\Dominio\Asistente\AsistenteVenta;
use App\Dominio\Asistente\ClaveIa;
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

/**
 * Ajustes → IA (v2, D194): asistente de venta con OpenAI. La clave va en el .env (OPENAI_API_KEY); aquí el modelo,
 * los límites y los precios por millón de tokens, y lo gastado este mes.
 *
 * @property-read Schema $form
 */
class AjustesIa extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.ajustes-generales';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'IA';

    protected static ?string $title = 'Inteligencia artificial';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'ajustes-ia';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Ajustes->permiso());
    }

    public function mount(): void
    {
        $this->form->fill(collect(['activa', 'modelo', 'mensajes_dia', 'mensajes_dia_nuevos', 'limite_mensual', 'precio_entrada', 'precio_salida'])->mapWithKeys(fn ($c) => [$c => Ajuste::obtener('ia.'.$c)])->all());
    }

    public function form(Schema $schema): Schema
    {
        $gastado = number_format(AsistenteVenta::gastoDelMes() / 100000, 2, ',', '.');

        return $schema->statePath('data')->components([
            Section::make('Asistente de venta')->columns(2)
                ->description('Chat para clientes con cuenta y el correo confirmado que consulta el catálogo y el motor de precios (nunca inventa precios). Si OpenAI falla, se oculta y la tienda sigue.'." Gastado este mes: {$gastado} €.")
                ->schema([
                    // D227: la clave se guarda cifrada y no se vuelve a enseñar; vacía, se conserva la que hubiera.
                    TextInput::make('clave')->label('Clave de OpenAI')->password()->revealable(false)->autocomplete('new-password')->maxLength(300)->columnSpanFull()
                        ->placeholder(fn () => match (ClaveIa::origen()) {
                            'env' => 'Configurada en el .env (manda sobre la del panel)',
                            'panel' => 'Configurada ✓ · escribe otra para cambiarla',
                            default => 'sk-…',
                        })
                        ->helperText('Se guarda cifrada y no se vuelve a mostrar. Si la APP_KEY del servidor cambia, hay que escribirla de nuevo.'),
                    Toggle::make('activa')->label('Mostrar el asistente a los clientes con cuenta')->columnSpanFull(),
                    TextInput::make('modelo')->label('Modelo de OpenAI')->required()->maxLength(60),
                    TextInput::make('mensajes_dia_nuevos')->label('Mensajes al día: clientes que aún no han comprado')->integer()->minValue(1)->maxValue(500)->required()
                        ->helperText('Cuota moderada para quien todavía no ha hecho ningún pedido.'),
                    TextInput::make('mensajes_dia')->label('Mensajes al día: clientes que ya han comprado')->integer()->minValue(1)->maxValue(500)->required()
                        ->helperText('A estos clientes el asistente les recuerda sus pedidos y les anima a repetir.'),
                    TextInput::make('limite_mensual')->label('Gasto máximo al mes')->suffix('€')->integer()->minValue(1)->maxValue(5000)->required()
                        ->helperText('Al llegar, el asistente se oculta hasta el mes siguiente.'),
                    TextInput::make('precio_entrada')->label('Precio por millón de tokens de entrada')->suffix('céntimos')->integer()->minValue(0)->required()
                        ->helperText('El de la tarifa de OpenAI para el modelo elegido.'),
                    TextInput::make('precio_salida')->label('Precio por millón de tokens de salida')->suffix('céntimos')->integer()->minValue(0)->required(),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('borrar_clave')->label('Borrar la clave')->color('danger')->requiresConfirmation()
                ->visible(fn () => ClaveIa::origen() === 'panel')
                ->modalDescription('El asistente y las ayudas de IA dejan de funcionar hasta que guardes otra.')
                ->action(function (): void {
                    ClaveIa::guardar(null);
                    Actividad::apuntar('ajustes', 'Clave de IA borrada');
                    Notification::make()->title('Clave borrada')->success()->send();
                }),
            Action::make('guardar')->label('Guardar cambios')->action(function (): void {
                $estado = $this->form->getState();
                if (filled($estado['clave'] ?? null)) {
                    ClaveIa::guardar((string) $estado['clave']);
                    Actividad::apuntar('ajustes', 'Clave de IA cambiada'); // nunca la clave
                }
                unset($estado['clave']);
                $this->data['clave'] = null;
                foreach ($estado as $clave => $valor) {
                    Ajuste::guardar('ia.'.$clave, $clave === 'modelo' ? trim((string) $valor) : ($clave === 'activa' ? (bool) $valor : (int) $valor));
                }
                Actividad::apuntar('ajustes', 'Ajustes de IA guardados');
                Notification::make()->title('Ajustes guardados')->body('Ya se aplican en la tienda.')->success()->send();
            }),
        ];
    }
}
