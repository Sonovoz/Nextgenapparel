<?php

namespace App\Filament\Acciones;

use App\Dominio\Catalogo\Proveedores\ClienteTopTex;
use App\Dominio\Catalogo\Proveedores\ImportarTopTex;
use App\Dominio\Catalogo\Proveedores\ProveedorNoDisponible;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Resources\Catalogo\Prendas\PrendaResource;
use App\Models\Familia;
use App\Models\Prenda;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * D254: «Importar de TopTex» (en Productos) y «Actualizar desde TopTex» (en la ficha de una prenda de TopTex).
 */
final class ImportarTopTexAction
{
    public static function importar(): Action
    {
        return Action::make('importar_toptex')->label('Importar de TopTex')->color('gray')->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn () => ClienteTopTex::configurado())
            ->modalHeading('Importar una prenda de TopTex')
            ->modalDescription('Trae la referencia con todos sus colores y tallas, el coste de TopTex y el precio de venta con el margen de Ajustes → Proveedores. La prenda se crea oculta: ponle fotos y zonas de impresión y actívala. Si ya estaba importada, se actualizan sus costes, precios, colores y tallas.')
            ->modalSubmitActionLabel('Importar')
            ->schema([
                TextInput::make('referencia')->label('Referencia de TopTex')->placeholder('GI6400B')->required()->maxLength(40)
                    ->regex('/^[A-Za-z0-9_-]+$/')->helperText('La del catálogo de TopTex («catalogReference»), sin el color ni la talla.'),
                Select::make('familia_id')->label('Familia')->required()->native(false)
                    ->options(fn () => Familia::orderBy('orden')->get()->mapWithKeys(fn (Familia $f) => [$f->getKey() => (string) $f->nombre])->all()),
            ])
            ->action(function (array $data, Action $action) {
                $resumen = self::ejecutar((string) $data['referencia'], Familia::find($data['familia_id']), $action);
                if ($resumen !== null) {
                    $action->redirect(PrendaResource::getUrl('edit', ['record' => $resumen]));
                }
            });
    }

    public static function actualizar(): Action
    {
        return Action::make('actualizar_toptex')->label('Actualizar desde TopTex')->color('gray')->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (Prenda $record) => ClienteTopTex::configurado() && $record->proveedor?->nombre === ImportarTopTex::PROVEEDOR && filled($record->referencia_proveedor))
            ->requiresConfirmation()
            ->modalDescription('Trae de TopTex los costes de hoy, recalcula los precios con el margen y añade los colores y tallas nuevos. Los textos, las fotos y las zonas no se tocan.')
            ->action(function (Prenda $record, Action $action) {
                if (self::ejecutar((string) $record->referencia_proveedor, null, $action) !== null) {
                    $action->redirect(PrendaResource::getUrl('edit', ['record' => $record]));
                }
            });
    }

    private static function ejecutar(string $referencia, ?Familia $familia, Action $action): ?Prenda
    {
        try {
            $r = app(ImportarTopTex::class)($referencia, $familia);
        } catch (ProveedorNoDisponible $e) {
            Notification::make()->title('No se ha podido importar')->body($e->getMessage())->danger()->persistent()->send();

            return null;
        }
        $prenda = $r['prenda'];
        Actividad::apuntar('catalogo', ($r['creada'] ? 'Prenda importada de TopTex: ' : 'Prenda actualizada desde TopTex: ').$prenda->referencia_proveedor);

        $avisos = array_filter([
            $r['sin_tarifa'] ? 'TopTex aún no da vuestra tarifa de precios (en una cuenta nueva tarda hasta 4 horas): las variaciones se han creado desactivadas y sin precio. Cuando la dé, pulsa «Actualizar desde TopTex» en la ficha y se activarán con su precio.' : null,
            ! $r['sin_tarifa'] && $r['sin_precio'] > 0 ? "{$r['sin_precio']} variaciones sin precio o sin peso en TopTex están desactivadas: no se venden." : null,
            $r['desactivadas'] > 0 ? "{$r['desactivadas']} variaciones desactivadas porque TopTex ya no las vende." : null,
            $r['fotos'] > 0 ? "{$r['fotos']} fotos de TopTex en camino: se descargan en segundo plano en unos minutos (las fotos que ya tengáis no se tocan)." : null,
            $r['fotos_bloqueadas'] ? 'Fotos: TopTex no las da hasta que aceptéis su carta de uso de fotos en su web (Photo library). Mientras, súbelas a mano.' : null,
            $r['creada'] ? 'La prenda está oculta: ponle fotos y zonas de impresión y activa «Visible en la tienda».' : null,
        ]);
        Notification::make()->success()->persistent()
            ->title(($r['creada'] ? 'Importada' : 'Actualizada').": {$r['colores']} colores, {$r['variantes_nuevas']} variaciones nuevas y {$r['variantes_actualizadas']} actualizadas")
            ->body($avisos === [] ? null : implode(' ', $avisos))
            ->send();

        return $prenda;
    }
}
