<?php

namespace App\Filament\Pages;

use App\Dominio\Informes\InformeVentas;
use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Analíticas → Informes de ventas (paso 8.2, D184): facturado, rectificado, neto, impuestos, pedidos,
 * ticket medio, ventas por día, lo más vendido y cupones, de un periodo; y el CSV de facturas.
 */
class Informes extends Page
{
    protected string $view = 'filament.pages.informes';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Analiticas;

    protected static ?string $navigationLabel = 'Informes de ventas';

    protected static ?string $title = 'Informes de ventas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'informes';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Analiticas->permiso());
    }

    public function mount(): void
    {
        $hoy = CarbonImmutable::now('Europe/Madrid');
        $this->desde = $this->fecha($this->desde) ?? $hoy->startOfMonth()->toDateString();
        $this->hasta = $this->fecha($this->hasta) ?? $hoy->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')->label('Descargar facturas (CSV)')->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (): StreamedResponse => response()->streamDownload(function (): void {
                    echo InformeVentas::csv(...$this->periodo());
                }, "facturas-{$this->desde}-{$this->hasta}.csv", ['Content-Type' => 'text/csv; charset=UTF-8'])),
        ];
    }

    protected function getViewData(): array
    {
        return ['datos' => InformeVentas::datos(...$this->periodo())];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function periodo(): array
    {
        $desde = CarbonImmutable::parse($this->fecha($this->desde) ?? 'first day of this month', 'Europe/Madrid');
        $hasta = CarbonImmutable::parse($this->fecha($this->hasta) ?? 'today', 'Europe/Madrid');

        return $desde->greaterThan($hasta) ? [$hasta, $desde] : [$desde, $hasta];
    }

    private function fecha(string $valor): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 ? $valor : null;
    }
}
