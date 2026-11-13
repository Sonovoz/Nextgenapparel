<?php

namespace App\Filament\Pages;

use App\Dominio\Disenos\LimpiarDisenos;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Dominio\Sistema\Almacenamiento\Almacenamiento as Medida;
use App\Dominio\Sistema\Almacenamiento\ZipArchivosPedidos;
use App\Dominio\Sistema\Copias\Copias;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Ajuste;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * Herramientas → Almacenamiento (D231): cuánto ocupa el proyecto por tipo frente al espacio máximo elegido,
 * borrar los archivos sin venta, descargar en ZIP los originales de pedidos que se van a borrar y la copia de
 * seguridad (base de datos y archivos) en ZIP.
 */
class Almacenamiento extends Page
{
    protected string $view = 'filament.pages.almacenamiento';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Almacenamiento';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Almacenamiento';

    protected static ?string $slug = 'almacenamiento';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Ajustes->permiso());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('limite')->label('Espacio máximo')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->color('gray')
                ->fillForm(fn () => ['limite_gb' => (int) Ajuste::obtener('almacenamiento.limite_gb')])
                ->schema([
                    Select::make('limite_gb')->label('Espacio máximo para el proyecto')->required()->native(false)
                        ->options(collect(Medida::OPCIONES_GB)->mapWithKeys(fn (int $gb) => [$gb => $gb.' GB'])->all())
                        ->helperText('El que tengas reservado en el servidor. La salud del sistema avisa al '.(int) Ajuste::obtener('almacenamiento.aviso_pct').' % y da fallo al '.(int) Ajuste::obtener('almacenamiento.fallo_pct').' %.'),
                ])
                ->action(function (array $data): void {
                    $antes = (int) Ajuste::obtener('almacenamiento.limite_gb');
                    Ajuste::guardar('almacenamiento.limite_gb', (int) $data['limite_gb']);
                    Actividad::apuntar('ajustes', 'Espacio máximo del proyecto cambiado', propiedades: ['old' => $antes, 'attributes' => (int) $data['limite_gb']]);
                    Notification::make()->success()->title('Espacio máximo: '.(int) $data['limite_gb'].' GB')->send();
                }),
            Action::make('borrar')->label('Borrar archivos sin venta')->icon(Heroicon::OutlinedTrash)->color('danger')
                ->requiresConfirmation()->modalHeading('Borrar archivos sin venta')
                ->modalDescription('Se borran los diseños que no han llegado a ningún pedido, también de «Mis diseños» de los clientes. No se puede deshacer.')
                ->schema([
                    Toggle::make('recientes')->label('Incluir también los subidos hace menos de '.(int) Ajuste::obtener('disenos.horas_sin_venta').' h')
                        ->helperText('Los que están en un carrito usado en ese plazo se conservan siempre: el cliente puede estar pagando.'),
                ])
                ->action(function (array $data): void {
                    $r = app(LimpiarDisenos::class)((bool) ($data['recientes'] ?? false));
                    Medida::medir(recalcular: true);
                    Actividad::apuntar('disenos', 'Archivos sin venta borrados desde el backend', propiedades: $r);
                    Notification::make()->success()->title("Borrados {$r['disenos']} diseños sin venta")->body("Y {$r['huerfanos']} archivos sueltos.")->send();
                }),
            Action::make('zip')->label('ZIP de originales que se borran pronto')->icon(Heroicon::OutlinedArchiveBoxArrowDown)->color('gray')
                ->requiresConfirmation()->modalHeading('Descargar los originales antes de que se borren')
                ->modalDescription(fn () => ($n = ZipArchivosPedidos::proximos()->count()) === 0
                    ? 'Ningún pedido borra sus originales en los próximos '.ZipArchivosPedidos::DIAS_AVISO.' días.'
                    : "{$n} pedidos borran sus originales en los próximos ".ZipArchivosPedidos::DIAS_AVISO.' días. Se descargan en un ZIP, una carpeta por pedido.')
                ->modalSubmitActionLabel('Descargar ZIP')
                ->action(function (): ?BinaryFileResponse {
                    $pedidos = ZipArchivosPedidos::proximos();
                    if ($pedidos->isEmpty()) {
                        Notification::make()->info()->title('No hay originales que se vayan a borrar pronto')->send();

                        return null;
                    }
                    Actividad::apuntar('pedidos', 'ZIP de originales de pedidos descargado', propiedades: ['pedidos' => $pedidos->count()]);

                    return response()->download(ZipArchivosPedidos::crear($pedidos), 'originales-pedidos-'.now()->format('Y-m-d').'.zip')->deleteFileAfterSend();
                }),
            Action::make('recalcular')->label('Recalcular')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->action(fn () => Medida::medir(recalcular: true)),
        ];
    }

    protected function getViewData(): array
    {
        $partes = Medida::medir();
        $total = array_sum(array_column($partes, 'bytes'));
        $libre = @disk_free_space(storage_path());
        $copia = collect(Copias::listar())->first();

        return [
            'partes' => $partes, 'total' => $total, 'limite' => Medida::limiteBytes(), 'porcentaje' => Medida::porcentaje(),
            'avisoPct' => (int) Ajuste::obtener('almacenamiento.aviso_pct'), 'falloPct' => (int) Ajuste::obtener('almacenamiento.fallo_pct'),
            'libre' => $libre === false ? null : (int) $libre, 'proximos' => ZipArchivosPedidos::proximos()->count(),
            'ultimaCopia' => $copia, 'urlCopias' => CopiasSeguridad::getUrl(),
        ];
    }
}
