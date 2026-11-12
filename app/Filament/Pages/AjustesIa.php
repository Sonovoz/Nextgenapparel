<?php

namespace App\Filament\Pages;

use App\Dominio\Asistente\AsistenteVenta;
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
        $this->form->fill(collect(['activa', 'modelo', 'mensajes_dia', 'limite_mensual', 'precio_entrada', 'precio_salida'])->mapWithKeys(fn ($c) => [$c => Ajuste::obtener('ia.'.$c)])->all());
    }

    public function form(Schema $schema): Schema
    {
        $gastado = number_format(AsistenteVenta::gastoDelMes() / 100000, 2, ',', '.');

        return $schema->statePath('data')->components([
            Section::make('Asistente de venta')->columns(2)
                ->description('Chat para clientes con cuenta que consulta el catálogo y el motor de precios (nunca inventa precios). Si OpenAI falla, se oculta y la tienda sigue. Clave: '
                    .(filled(config('ia.openai_key')) ? 'configurada en el .env.' : 'falta OPENAI_API_KEY en el .env.')." Gastado este mes: {$gastado} €.")
                ->schema([
                    Toggle::make('activa')->label('Mostrar el asistente a los clientes con cuenta')->columnSpanFull(),
                    TextInput::make('modelo')->label('Modelo de OpenAI')->required()->maxLength(60),
                    TextInput::make('mensajes_dia')->label('Mensajes por cliente y día')->integer()->minValue(1)->maxValue(500)->required(),
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
            Action::make('guardar')->label('Guardar cambios')->action(function (): void {
                foreach ($this->form->getState() as $clave => $valor) {
                    Ajuste::guardar('ia.'.$clave, $clave === 'modelo' ? trim((string) $valor) : ($clave === 'activa' ? (bool) $valor : (int) $valor));
                }
                Actividad::apuntar('ajustes', 'Ajustes de IA guardados');
                Notification::make()->title('Ajustes guardados')->body('Ya se aplican en la tienda.')->success()->send();
            }),
        ];
    }
}
