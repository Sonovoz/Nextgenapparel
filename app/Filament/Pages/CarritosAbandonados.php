<?php

namespace App\Filament\Pages;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Ajuste;
use App\Models\AvisoCarrito;
use App\Models\Cupon;
use App\Models\Pedido;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
 * Marketing → Carritos abandonados (v2, D189): la secuencia de avisos y lo que recuperan. Solo a clientes con
 * cuenta, correo verificado y consentimiento comercial.
 *
 * @property-read Schema $form
 */
class CarritosAbandonados extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.carritos-abandonados';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Marketing;

    protected static ?string $navigationLabel = 'Carritos abandonados';

    protected static ?string $title = 'Carritos abandonados';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'carritos-abandonados';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Marketing->permiso());
    }

    public function mount(): void
    {
        $this->form->fill(['activos' => (bool) Ajuste::obtener('carritos.avisos_activos'), 'secuencia' => array_values((array) Ajuste::obtener('carritos.secuencia'))]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Avisos')
                ->description('Se envían a los clientes con cuenta, el correo verificado y el consentimiento comercial que dejan prendas en el carrito. Cuentan desde el último cambio del carrito; si compra, se paran solos. Cada correo lleva el enlace para darse de baja.')
                ->schema([
                    Toggle::make('activos')->label('Enviar avisos de carritos abandonados'),
                    Repeater::make('secuencia')->label('Secuencia')->columns(2)->minItems(1)->maxItems(5)->reorderable(false)->addActionLabel('Añadir aviso')
                        ->schema([
                            TextInput::make('horas')->label('Horas desde el último cambio')->integer()->minValue(1)->maxValue(720)->required(),
                            Select::make('cupon')->label('Cupón (opcional)')->native(false)->placeholder('Sin cupón')
                                ->options(fn () => Cupon::where('activo', true)->orderBy('codigo')->pluck('codigo', 'codigo')->all()),
                        ]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('guardar')->label('Guardar cambios')->action(function (): void {
                $datos = $this->form->getState();
                $secuencia = collect($datos['secuencia'] ?? [])->map(fn ($p) => ['horas' => (int) $p['horas'], 'cupon' => $p['cupon'] ?? null])->sortBy('horas')->values()->all();
                Ajuste::guardar('carritos.avisos_activos', (bool) $datos['activos']);
                Ajuste::guardar('carritos.secuencia', $secuencia);
                Actividad::apuntar('ajustes', 'Carritos abandonados: ajustes guardados', propiedades: ['activos' => (bool) $datos['activos'], 'secuencia' => $secuencia]);
                Notification::make()->title('Ajustes guardados')->success()->send();
            }),
        ];
    }

    protected function getViewData(): array
    {
        $avisos = AvisoCarrito::where('enviado_en', '>=', now()->subDays(30))->get();
        // Recuperado: el cliente avisado hace un pedido en los 7 días siguientes al aviso.
        $recuperados = $avisos->groupBy('cliente_id')->filter(fn ($g, $cliente) => Pedido::where('cliente_id', $cliente)
            ->where('created_at', '>=', $g->min('enviado_en'))->where('created_at', '<=', $g->max('enviado_en')->copy()->addDays(7))->exists())->count();

        return ['porPaso' => $avisos->countBy('paso')->sortKeys()->all(), 'clientes' => $avisos->pluck('cliente_id')->unique()->count(), 'recuperados' => $recuperados];
    }
}
