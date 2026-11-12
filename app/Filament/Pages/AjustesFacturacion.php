<?php

namespace App\Filament\Pages;

use App\Dominio\Facturacion\Emisor;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Ajuste;
use App\Models\Factura;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

/**
 * Ajustes → Facturación (paso 6.5a, D175): los datos fiscales de la tienda y las series.
 * Sin datos fiscales no se factura; al completarlos se emiten las pendientes. Las series no
 * se cambian después de la primera factura (la numeración es correlativa).
 *
 * @property-read Schema $form
 */
class AjustesFacturacion extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.ajustes-generales';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'Facturación';

    protected static ?string $title = 'Facturación';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'ajustes-facturacion';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Ajustes->permiso());
    }

    public function mount(): void
    {
        $this->form->fill(['emisor' => Emisor::datos(), 'series' => (array) Ajuste::obtener('facturacion.series')]);
    }

    public function form(Schema $schema): Schema
    {
        $hayFacturas = Factura::exists();

        return $schema->statePath('data')->components([
            Section::make('Datos fiscales de la tienda')
                ->description('Salen en todas las facturas. Mientras falte alguno obligatorio no se factura: los pedidos quedan con la factura pendiente y se emiten al completarlos. Confírmalos con la asesoría.')
                ->columns(2)
                ->schema([
                    TextInput::make('emisor.razon_social')->label('Razón social')->required()->maxLength(150),
                    TextInput::make('emisor.nif')->label('NIF')->required()->maxLength(20),
                    TextInput::make('emisor.direccion')->label('Dirección')->required()->maxLength(200)->columnSpanFull(),
                    TextInput::make('emisor.codigo_postal')->label('Código postal')->required()->maxLength(10),
                    TextInput::make('emisor.localidad')->label('Localidad')->required()->maxLength(100),
                    TextInput::make('emisor.provincia')->label('Provincia')->maxLength(100),
                    TextInput::make('emisor.pais')->label('País (código)')->maxLength(2)->default('ES'),
                    TextInput::make('emisor.correo')->label('Correo de facturación')->email()->maxLength(190),
                ]),
            Section::make('Series')
                ->description($hayFacturas
                    ? 'Ya hay facturas emitidas: las series no se pueden cambiar (la numeración es correlativa por serie y año).'
                    : 'Letras de cada serie: F2026-000001. No se pueden cambiar después de la primera factura.')
                ->columns(3)
                ->schema([
                    TextInput::make('series.completa')->label('Facturas (F1)')->required()->maxLength(5)->regex('/^[A-Z]+$/')->disabled($hayFacturas),
                    TextInput::make('series.simplificada')->label('Simplificadas (F2)')->required()->maxLength(5)->regex('/^[A-Z]+$/')->disabled($hayFacturas),
                    TextInput::make('series.rectificativa')->label('Rectificativas (R1 y R5)')->required()->maxLength(5)->regex('/^[A-Z]+$/')->disabled($hayFacturas)
                        ->different('series.completa')->different('series.simplificada'),
                ]),
            Section::make('Veri*Factu')->schema([
                TextInput::make('verifactu')->label('Estado')->disabled()->dehydrated(false)
                    ->formatStateUsing(fn () => config('facturacion.verifactu.activo')
                        ? 'Activado ('.(config('facturacion.verifactu.produccion') ? 'producción' : 'pruebas').')'
                        : 'Desactivado hasta que sea obligatorio: la huella encadenada se guarda igualmente.'),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('guardar')->label('Guardar cambios')->action(function (): void {
                $datos = $this->form->getState();
                $antes = Emisor::datos();
                Ajuste::guardar('facturacion.emisor', array_map(fn ($v) => blank($v) ? null : trim((string) $v), $datos['emisor']));
                if (! Factura::exists() && isset($datos['series'])) {
                    Ajuste::guardar('facturacion.series', array_map(fn ($v) => mb_strtoupper(trim((string) $v)), $datos['series']));
                }
                if ($antes !== Emisor::datos()) {
                    Actividad::apuntar('ajustes', 'Datos fiscales de la tienda cambiados');
                }
                if (Emisor::falta() === []) {
                    Artisan::queue('facturas:emitir-pendientes');
                }
                Notification::make()->title('Ajustes guardados')->body('Ya se aplican en la tienda.')->success()->send();
            }),
        ];
    }
}
