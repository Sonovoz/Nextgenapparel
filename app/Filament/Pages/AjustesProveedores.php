<?php

namespace App\Filament\Pages;

use App\Dominio\Catalogo\Proveedores\ClienteTopTex;
use App\Dominio\Catalogo\Proveedores\ImportarTopTex;
use App\Dominio\Catalogo\Proveedores\ProveedorNoDisponible;
use App\Dominio\Precios\PrecioConMargen;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Ajuste;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Ajustes → Proveedores (D253): margen comercial general sobre el coste de las prendas de proveedor y estado de la
 * conexión con TopTex. Las credenciales de TopTex van solo en el .env (TOPTEX_API_KEY, TOPTEX_USUARIO, TOPTEX_CLAVE).
 *
 * @property-read Schema $form
 */
class AjustesProveedores extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.ajustes-generales';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'Proveedores';

    protected static ?string $title = 'Proveedores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'ajustes-proveedores';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Ajustes->permiso());
    }

    public function mount(): void
    {
        $this->form->fill(['margen' => Ajuste::obtener('proveedores.margen')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Margen comercial')->columns(2)
                ->description('El precio de venta (sin IVA) de una prenda de proveedor es su coste más este margen, redondeado al alza a 0,05 €. Cada prenda puede tener su propio margen en su ficha.')
                ->schema([
                    TextInput::make('margen')->label('Margen general sobre el coste')->integer()->minValue(0)->maxValue(1000)->suffix('%')->required()->live(debounce: 400),
                    TextEntry::make('ejemplo')->label('Ejemplo')
                        ->state(function (Get $get): string {
                            $venta = PrecioConMargen::venta(500, (int) $get('margen'));

                            return 'Una prenda que nos cuesta 5,00 € se vende a '.number_format($venta / 100, 2, ',', '.').' € sin IVA.';
                        }),
                ]),
            Section::make('TopTex')
                ->description('Las credenciales van solo en el .env del servidor: TOPTEX_API_KEY (la API key de «My Dashboard» del portal de TopTex), TOPTEX_USUARIO (empresa_cliente, en minúsculas) y TOPTEX_CLAVE. Nunca aquí ni por correo.')
                ->schema([
                    TextEntry::make('estado')->label('Conexión')
                        ->state(fn (): string => ClienteTopTex::configurado() ? 'Credenciales en el .env ✓ · pulsa «Probar conexión con TopTex»' : 'Faltan las credenciales en el .env'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('probar')->label('Probar conexión con TopTex')->color('gray')
                ->action(function (ClienteTopTex $toptex): void {
                    try {
                        $usuario = $toptex->probar();
                    } catch (ProveedorNoDisponible $e) {
                        Notification::make()->title('No se ha podido conectar')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }
                    Notification::make()->title('Conexión correcta')->body('TopTex acepta las credenciales ('.$usuario.').')->success()->send();
                }),
            Action::make('guardar')->label('Guardar cambios')->action(function (): void {
                $estado = $this->form->getState();
                Ajuste::guardar('proveedores.margen', (int) $estado['margen']);
                $cambiadas = ImportarTopTex::recalcular(); // D254: los precios de las prendas de proveedor siguen al margen
                Actividad::apuntar('ajustes', 'Margen general de proveedores: '.(int) $estado['margen'].' %');
                Notification::make()->title('Ajustes guardados')->body($cambiadas > 0 ? "{$cambiadas} precios de prendas de proveedor recalculados." : null)->success()->send();
            }),
        ];
    }
}
