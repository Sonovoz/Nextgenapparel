<?php

namespace App\Filament\Resources\Disenos;

use App\Dominio\Disenos\Calidad;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Filament\Resources\Disenos\Pages\ListDisenos;
use App\Filament\Resources\Disenos\Pages\ViewDiseno;
use App\Models\Diseno;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Biblioteca de diseños (fase 4), en Clientes como en la ficha § 4. Solo lectura:
 * los diseños los suben los clientes. Se puede descargar el original y borrarlo
 * (por ejemplo, a petición del cliente); ambas cosas quedan en el registro de actividad.
 */
class DisenoResource extends Resource
{
    protected static ?string $model = Diseno::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Clientes;

    protected static ?string $navigationLabel = 'Biblioteca de diseños';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'diseño';

    protected static ?string $pluralModelLabel = 'diseños';

    protected static ?string $slug = 'disenos';

    public const FONDOS = ['transparente' => 'Transparente', 'fondo' => 'Con fondo', 'fondo_blanco' => 'Fondo blanco'];

    public const ESTADOS = ['subido' => 'Subido', 'aprobado' => 'Aprobado', 'requiere_cambios' => 'Requiere cambios', 'rechazado' => 'Rechazado'];

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Todavía no hay diseños')
            ->emptyStateDescription('Aparecen aquí cuando un cliente sube su PNG en el configurador.')
            ->columns([
                ImageColumn::make('vista')->label('')
                    ->state(fn (Diseno $record): string => route('backend.disenos.miniatura', $record))
                    ->imageHeight(56)->extraImgAttributes(['style' => 'object-fit: contain; background: repeating-conic-gradient(#e5e7eb 0 25%, #fff 0 50%) 0 0 / 12px 12px;']),
                TextColumn::make('nombre_original')->label('Archivo')->searchable()->wrap(),
                TextColumn::make('medidas')->label('Se imprime')
                    ->state(fn (Diseno $record): string => $record->util_ancho_px.' × '.$record->util_alto_px.' px')
                    ->description(fn (Diseno $record): string => 'Excelente hasta '.self::tamanoExcelente($record)),
                TextColumn::make('fondo')->label('Fondo')->badge()
                    ->formatStateUsing(fn (string $state): string => self::FONDOS[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'transparente' ? 'success' : 'warning'),
                TextColumn::make('perfil_color')->label('Color')->placeholder('Sin perfil'),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => self::ESTADOS[$state] ?? $state),
                TextColumn::make('dueno')->label('De')->state(fn (Diseno $record): string => $record->dueno()),
                TextColumn::make('created_at')->label('Subido')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('fondo')->label('Fondo')->options(self::FONDOS),
                SelectFilter::make('estado')->label('Estado')->options(self::ESTADOS),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                self::accionDescargar(),
                self::accionBorrar(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Lo que se imprime')->columnSpan(1)->schema([
                ImageEntry::make('vista')->hiddenLabel()
                    ->state(fn (Diseno $record): string => route('backend.disenos.miniatura', $record))
                    ->imageHeight(320)->extraImgAttributes(['style' => 'width: 100%; max-width: 100%; object-fit: contain; background: repeating-conic-gradient(#e5e7eb 0 25%, #fff 0 50%) 0 0 / 16px 16px;']),
            ]),
            Section::make('Datos del archivo')->columnSpan(2)->columns(2)->schema([
                TextEntry::make('nombre_original')->label('Archivo'),
                TextEntry::make('bytes')->label('Peso')->formatStateUsing(fn (int $state): string => number_format($state / 1048576, 2, ',', '.').' MB'),
                TextEntry::make('lienzo')->label('Lienzo')->state(fn (Diseno $record): string => $record->ancho_px.' × '.$record->alto_px.' px'),
                TextEntry::make('util')->label('Se imprime (sin márgenes transparentes)')
                    ->state(fn (Diseno $record): string => $record->util_ancho_px.' × '.$record->util_alto_px.' px, desde ('.$record->util_x.', '.$record->util_y.')'),
                TextEntry::make('excelente')->label('Calidad excelente hasta')->state(fn (Diseno $record): string => self::tamanoExcelente($record)),
                TextEntry::make('fondo')->label('Fondo')->badge()->formatStateUsing(fn (string $state): string => self::FONDOS[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'transparente' ? 'success' : 'warning'),
                TextEntry::make('perfil_color')->label('Perfil de color')->placeholder('Sin perfil (se trata como sRGB)'),
                TextEntry::make('profundidad_bits')->label('Profundidad')->suffix(' bits por canal'),
                TextEntry::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => self::ESTADOS[$state] ?? $state),
                TextEntry::make('dueno')->label('De')->state(fn (Diseno $record): string => $record->dueno()),
            ]),
            Section::make('Declaración de derechos')->columnSpanFull()->columns(3)->schema([
                // Desde D202, la declaración va en las condiciones de venta que acepta al confirmar el pedido.
                TextEntry::make('derechos_texto')->label('Texto aceptado')->columnSpan(2)->placeholder('En las condiciones de venta del pedido'),
                TextEntry::make('derechos_declarados_en')->label('Aceptado el')->dateTime('d/m/Y H:i:s')->placeholder('Al confirmar el pedido'),
            ]),
        ]);
    }

    /** Ancho × alto máximos con calidad excelente (umbral de Ajustes), en cm. */
    public static function tamanoExcelente(Diseno $diseno): string
    {
        $ppp = Calidad::umbrales()['correcto'];
        $cm = fn (int $px) => rtrim(rtrim(number_format(Calidad::tamanoMaximoMm($px, $ppp) / 10, 1, ',', ''), '0'), ',');

        return $cm($diseno->util_ancho_px).' × '.$cm($diseno->util_alto_px).' cm';
    }

    public static function accionDescargar(): Action
    {
        return Action::make('descargar')->label('Descargar original')->icon(Heroicon::OutlinedArrowDownTray)
            ->url(fn (Diseno $record): string => route('backend.disenos.original', $record));
    }

    public static function accionBorrar(): DeleteAction
    {
        return DeleteAction::make()->label('Borrar')
            ->modalDescription('Se borran el diseño y sus archivos. No se puede deshacer.')
            ->after(fn (Diseno $record) => Actividad::apuntar('disenos', 'Diseño borrado', propiedades: [
                'archivo' => $record->nombre_original, 'uuid' => $record->uuid, 'de' => $record->dueno(),
            ]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDisenos::route('/'),
            'view' => ViewDiseno::route('/{record}'),
        ];
    }
}
