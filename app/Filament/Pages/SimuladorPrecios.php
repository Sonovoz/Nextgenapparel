<?php

namespace App\Filament\Pages;

use App\Dominio\Precios\Motor\Cesta;
use App\Dominio\Precios\Motor\Destino;
use App\Dominio\Precios\Motor\LineaCesta;
use App\Dominio\Precios\Motor\MotorPrecios;
use App\Dominio\Precios\Motor\PackCesta;
use App\Dominio\Precios\Motor\Resultado;
use App\Dominio\Precios\RepartoPack;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Color;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\Variante;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Marketing → Simulador de precios: monta una cesta y muestra paso a paso lo
 * que calcularía el motor (ofertas, cupón, mínimos, envío, impuestos). Para
 * probar las reglas antes de publicarlas. No guarda nada.
 */
class SimuladorPrecios extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.simulador-precios';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Marketing;

    protected static ?string $navigationLabel = 'Simulador de precios';

    protected static ?string $title = 'Simulador de precios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'simulador-precios';

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $resultado = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Marketing->permiso());
    }

    public function mount(): void
    {
        $this->form->fill(['pais' => 'ES', 'lineas' => [[]]]);
    }

    public function form(Schema $schema): Schema
    {
        $tallas = Repeater::make('tallas')->label('Tallas')->columns(2)->defaultItems(1)->addActionLabel('Añadir talla')->schema([
            Select::make('talla_id')->label('Talla')->required()->native(false)->distinct()->options(fn () => Talla::orderBy('orden')->pluck('nombre', 'id')),
            TextInput::make('unidades')->label('Unidades')->integer()->minValue(1)->required(),
        ]);

        return $schema->statePath('data')->components([
            Section::make('Prendas sueltas')->schema([
                Repeater::make('lineas')->label('')->columns(3)->defaultItems(0)->addActionLabel('Añadir prenda')->schema([
                    Select::make('prenda_id')->label('Prenda')->required()->native(false)->live()
                        ->options(fn () => Prenda::where('activa', true)->orderBy('id')->get()->pluck('nombre', 'id')),
                    Select::make('color_id')->label('Color')->required()->native(false)
                        ->options(fn (Get $get) => Color::whereIn('id', Variante::where('prenda_id', $get('prenda_id'))->aLaVenta()->pluck('color_id'))->orderBy('orden')->get()->pluck('nombre', 'id')),
                    Select::make('zonas')->label('Zonas')->multiple()->native(false)
                        ->options(fn (Get $get) => Prenda::find($get('prenda_id'))?->zonas()->where('zonas_impresion.activa', true)->get()->pluck('nombre', 'id') ?? []),
                    (clone $tallas)->columnSpanFull(),
                ]),
            ]),
            Section::make('Packs')->collapsed()->schema([
                Repeater::make('packs')->label('')->columns(2)->defaultItems(0)->addActionLabel('Añadir pack')->schema([
                    Select::make('pack_id')->label('Pack')->required()->native(false)->live()
                        ->options(fn () => Pack::where('activo', true)->orderBy('orden')->get()->pluck('nombre', 'id')),
                    Select::make('color_id')->label('Color (si el cliente elige)')->native(false)
                        ->visible(fn (Get $get) => Pack::find($get('pack_id'))?->reparto === RepartoPack::Libre)
                        ->options(fn (Get $get) => Color::whereIn('id', Pack::find($get('pack_id'))?->lineas()->first()?->colores ?? [])->get()->pluck('nombre', 'id')),
                    (clone $tallas)->label('Reparto de tallas (si el cliente elige)')->columnSpanFull()->defaultItems(0)
                        ->visible(fn (Get $get) => Pack::find($get('pack_id'))?->reparto === RepartoPack::Libre),
                ]),
            ]),
            Section::make('Cliente y envío')->schema([
                Grid::make(4)->schema([
                    TextInput::make('cupon')->label('Cupón')->maxLength(40),
                    Toggle::make('recargo')->label('Recargo de equivalencia')->inline(false),
                    TextInput::make('pais')->label('País (ISO)')->maxLength(2)->placeholder('ES'),
                    TextInput::make('cp')->label('Código postal')->maxLength(10)->helperText('Vacío: sin envío.'),
                ]),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('calcular')->label('Calcular')->icon(Heroicon::OutlinedCalculator)->action(fn () => $this->calcular())];
    }

    public function calcular(): void
    {
        $datos = $this->form->getState();
        $tallas = fn (array $filas) => collect($filas)->filter(fn ($f) => filled($f['talla_id'] ?? null))
            ->mapWithKeys(fn ($f) => [(int) $f['talla_id'] => (int) $f['unidades']])->all();

        $lineas = collect($datos['lineas'] ?? [])->map(fn ($l) => new LineaCesta(
            (int) $l['prenda_id'], (int) $l['color_id'], $tallas($l['tallas'] ?? []), array_map('intval', $l['zonas'] ?? []),
        ))->all();
        $packs = collect($datos['packs'] ?? [])->map(function ($p) use ($tallas) {
            $pack = Pack::find($p['pack_id']);
            $elecciones = $pack?->reparto === RepartoPack::Libre && ($linea = $pack->lineas()->first())
                ? [$linea->id => ['color' => (int) ($p['color_id'] ?? 0), 'tallas' => $tallas($p['tallas'] ?? [])]]
                : [];

            return new PackCesta((int) $p['pack_id'], $elecciones);
        })->all();

        $destino = filled($datos['cp'] ?? null) ? new Destino((string) ($datos['pais'] ?: 'ES'), (string) $datos['cp']) : null;
        $resultado = app(MotorPrecios::class)->calcular(new Cesta($lineas, $packs, $datos['cupon'] ?? null, (bool) ($datos['recargo'] ?? false), $destino));

        $this->resultado = $this->presentar($resultado);
    }

    /** @return array<string, mixed> */
    private function presentar(Resultado $r): array
    {
        return [
            'lineas' => array_map(fn ($l) => $l->aArray(), $r->lineas),
            'oferta' => $r->oferta, 'cupon' => $r->cupon, 'envio' => $r->envio, 'impuestos' => $r->impuestos,
            'bruto' => $r->bruto(), 'descuentos' => $r->descuentos(), 'subtotal' => $r->subtotal(),
            'base' => $r->baseImponible(), 'total_impuestos' => $r->totalImpuestos(), 'total' => $r->total(),
            'unidades' => $r->unidades(), 'confirmable' => $r->confirmable(),
            'avisos' => $r->textos('avisos', 'es'), 'impedimentos' => $r->textos('impedimentos', 'es'),
        ];
    }
}
