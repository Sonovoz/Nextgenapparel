<?php

namespace App\Filament\Resources\Precios\Pages;

use App\Dominio\Asistente\AsistentePacks;
use App\Dominio\Asistente\IaNoDisponible;
use App\Dominio\Asistente\RedactorSeo;
use App\Dominio\Catalogo\ZonasPack;
use App\Dominio\Precios\Formato;
use App\Dominio\Precios\RepartoPack;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Concerns\CampoDinero;
use App\Filament\Resources\Precios\PackResource;
use App\Models\Color;
use App\Models\Talla;
use App\Models\ZonaImpresion;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ManagePacks extends ManageRecords
{
    protected static string $resource = PackResource::class;

    public function getTitle(): string
    {
        return 'Packs';
    }

    protected function getHeaderActions(): array
    {
        return [PackResource::accionCrear()->slideOver(), self::conIa()];
    }

    /**
     * D233: «Crear con IA». Paso 1: qué pack se quiere. Paso 2: la propuesta (composición y textos de la IA) con el
     * cálculo de la tienda (precio de catálogo, coste, precio sugerido y margen) y el precio que decide el equipo.
     */
    public static function conIa(): Action
    {
        return Action::make('ia')->label('Crear con IA')->icon(Heroicon::OutlinedSparkles)->color('gray')
            ->visible(fn () => RedactorSeo::disponible())
            ->modalHeading('Crear un pack con IA')->modalWidth(Width::FourExtraLarge)->modalSubmitActionLabel('Crear el pack')
            ->steps([
                Step::make('Idea')->description('Qué pack quieres')->schema([
                    Textarea::make('descripcion')->label('Describe el pack')->rows(5)->required()->maxLength(2000)
                        ->placeholder("Ej.: pack para eventos con 50 camisetas negras y blancas, impresión delante y detrás, tallas a elegir, para festivales.\nO: pack de empresa con 20 polos y 20 sudaderas con el logo en el pecho."),
                    Hidden::make('propuesta'),
                ])->afterValidation(function (Get $get, Set $set): void {
                    try {
                        $propuesta = app(AsistentePacks::class)->proponer((string) $get('descripcion'));
                    } catch (IaNoDisponible $e) {
                        Notification::make()->danger()->title('La IA no ha podido proponer el pack')->body($e->getMessage())->send();

                        throw new Halt;
                    }
                    if ($propuesta['lineas'] === [] || $propuesta['zonas'] === []) {
                        Notification::make()->warning()->title('No he podido montar un pack con el catálogo')
                            ->body(implode(' ', $propuesta['descartes']) ?: 'Prueba a nombrar las prendas y las zonas.')->persistent()->send();

                        throw new Halt;
                    }
                    $set('propuesta', (string) json_encode($propuesta, JSON_UNESCAPED_UNICODE));
                    $set('precio', number_format(AsistentePacks::valorar($propuesta)['sugerido'] / 100, 2, ',', ''));
                }),
                Step::make('Revisa')->description('El precio lo decides tú')->schema([
                    Placeholder::make('vista')->hiddenLabel()
                        ->content(fn (Get $get) => self::vistaPrevia((array) json_decode((string) $get('propuesta'), true), filled($get('precio')) ? CampoDinero::aCentimos($get('precio')) : null)),
                    CampoDinero::make('precio', 'Precio cerrado del pack (sin IVA)')->required()->live(onBlur: true)
                        ->helperText('Propuesto: el de catálogo menos el descuento sugerido por la IA, redondeado al euro. Cámbialo si quieres.'),
                    Toggle::make('activo')->label('Publicarlo ya')->default(false)
                        ->helperText('Apagado: se crea inactivo para que añadas la imagen y lo revises en la lista.'),
                ]),
            ])
            ->action(function (array $data): void {
                $pack = AsistentePacks::crear((array) json_decode((string) ($data['propuesta'] ?? ''), true), (int) $data['precio'], (bool) ($data['activo'] ?? false));
                Actividad::apuntar('precios', 'Pack creado con IA', $pack, ['precio' => $pack->precio]);
                Notification::make()->success()->title('Pack «'.$pack->nombre.'» creado')
                    ->body($pack->activo ? 'Ya se vende en la tienda.' : 'Está inactivo: añade la imagen y actívalo en la lista.')->send();
            });
    }

    /** @param  array<string, mixed>  $propuesta */
    public static function vistaPrevia(array $propuesta, ?int $precio): HtmlString
    {
        if ($propuesta === []) {
            return new HtmlString('');
        }
        $p = AsistentePacks::normalizar($propuesta);
        $v = AsistentePacks::valorar($p, $precio);
        $zonas = ZonaImpresion::whereIn('id', $p['zonas'])->get()->map(fn ($z) => (string) $z->nombre)->implode(', ');
        $filas = collect($p['lineas'])->map(function (array $l) use ($p) {
            $colores = Color::whereIn('id', $l['colores'])->get()->map(fn ($c) => (string) $c->nombre)->implode(', ');
            $tallas = $p['reparto'] === RepartoPack::Fijo->value
                ? collect($l['reparto_tallas'])->map(fn ($u, $t) => Talla::find($t)?->nombre.' × '.$u)->implode(' · ')
                : Talla::whereIn('id', $l['tallas'])->orderBy('orden')->pluck('nombre')->implode(', ');

            return '<li><strong>'.e($l['unidades'].' × '.$l['prenda']).'</strong> · '.e($colores).' · '.e($tallas)
                .' · impresión: '.e(ZonasPack::nombres($p['zonas'], $l['prenda_id']) ?: '—').'</li>';
        })->implode('');
        $margenColor = $v['margen'] < 0 ? '#dc2626' : ($v['margen_pct'] < 20 ? '#d97706' : '#16a34a');

        return new HtmlString(
            '<p style="margin:0 0 4px;font-size:1.05rem"><strong>'.e($p['nombre']['es']).'</strong>'.($p['nombre']['en'] ? ' <span style="opacity:.6">/ '.e($p['nombre']['en']).'</span>' : '').'</p>'
            .'<p style="margin:0 0 8px;opacity:.8">'.e($p['descripcion']['es']).'</p>'
            .'<ul style="margin:0 0 8px;padding-left:1.1rem">'.$filas.'</ul>'
            .'<p style="margin:0 0 8px;opacity:.8">Zonas: '.e($zonas).' · Tallas y color: '.e($p['reparto'] === RepartoPack::Fijo->value ? 'fijos' : 'los elige el cliente')
            .' · Diseños: hasta '.$p['max_disenos'].($p['modos'] ? ' · Modos: '.e(implode(', ', $p['modos'])) : '').'</p>'
            .'<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:8px 0;padding:10px;border-radius:10px;background:rgba(127,127,127,.08)">'
            .'<div><small style="opacity:.7">Catálogo (motor)</small><br><strong>'.Formato::euros($v['catalogo']).'</strong></div>'
            .'<div><small style="opacity:.7">Sugerido (−'.$p['descuento_pct'].' %)</small><br><strong>'.Formato::euros($v['sugerido']).'</strong></div>'
            .'<div><small style="opacity:.7">Coste</small><br><strong>'.Formato::euros($v['coste']).'</strong></div>'
            .'<div><small style="opacity:.7">Margen con tu precio</small><br><strong style="color:'.$margenColor.'">'.Formato::euros($v['margen']).' ('.$v['margen_pct'].' %)</strong></div>'
            .'</div>'
            .($p['motivo'] ? '<p style="margin:0 0 6px;opacity:.8">IA: '.e($p['motivo']).'</p>' : '')
            .($p['descartes'] ? '<p style="margin:0;color:#d97706">⚠ '.implode('<br>⚠ ', array_map('e', $p['descartes'])).'</p>' : '')
            .'<p style="margin:6px 0 0;opacity:.6;font-size:.8rem">Importes sin IVA. Los calcula la tienda con su motor de precios; la IA solo propone la composición, los textos y el porcentaje.</p>'
        );
    }
}
