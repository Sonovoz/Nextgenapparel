<?php

namespace App\Filament\Resources\Precios;

use App\Dominio\Catalogo\ModoCliente;
use App\Dominio\Catalogo\ZonasPack;
use App\Dominio\Precios\Impuestos;
use App\Dominio\Precios\Iva;
use App\Dominio\Precios\RepartoPack;
use App\Filament\Concerns\BusquedaTraducible;
use App\Filament\Concerns\CampoDinero;
use App\Filament\Concerns\CamposTraducibles;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\ClaseImpuesto;
use App\Models\Color;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use BackedEnum;
use Closure;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use UnitEnum;

/** Productos → Packs: ofertas cerradas con precio fijo (ficha § 6 bis). */
class PackResource extends RecursoPrecios
{
    protected static ?string $model = Pack::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Productos;

    protected static ?string $navigationLabel = 'Packs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'pack';

    protected static ?string $pluralModelLabel = 'packs';

    protected static ?string $slug = 'packs';

    /** D223: «Polo: frontal, espalda (sin Pecho) · Camiseta: frontal, espalda, Pecho». */
    private static function resumenZonas(Get $get): HtmlString
    {
        $zonas = array_map('intval', $get('zonas') ?? []);
        $prendas = collect($get('lineas') ?? [])->pluck('prenda_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        if ($zonas === [] || $prendas === []) {
            return new HtmlString('Cada prenda se imprime en las zonas que ofrece; otras zonas se cobran aparte con la tarifa de cada prenda.');
        }
        $filas = array_map(fn (array $r) => '<strong>'.e($r['prenda']).'</strong>: '.e($r['si'] === [] ? 'ninguna' : implode(', ', $r['si']))
            .($r['no'] !== [] ? ' <span style="opacity:.7">(sin '.e(implode(', ', $r['no'])).')</span>' : ''), ZonasPack::resumen($zonas, $prendas));

        return new HtmlString('Se imprime en: '.implode(' · ', $filas).'<br>Otras zonas se cobran aparte con la tarifa de cada prenda.');
    }

    private static function esFijo(Get $get, string $ruta = 'reparto'): bool
    {
        $valor = $get($ruta);

        return ($valor instanceof RepartoPack ? $valor : RepartoPack::tryFrom((string) $valor)) !== RepartoPack::Libre;
    }

    /** @return list<array{talla_id: int, unidades: int}> */
    private static function filasReparto(?array $reparto): array
    {
        return collect($reparto ?? [])->map(fn ($unidades, $talla) => ['talla_id' => (int) $talla, 'unidades' => (int) $unidades])->values()->all();
    }

    private static function guardarReparto(array $data): array
    {
        $filas = $data['reparto_tallas'] ?? [];
        $data['reparto_tallas'] = $filas === [] ? null : collect($filas)->filter(fn ($f) => filled($f['talla_id'] ?? null))
            ->mapWithKeys(fn ($f) => [(int) $f['talla_id'] => (int) $f['unidades']])->all();

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Pack')->schema([
                CamposTraducibles::texto('nombre', 'Nombre', obligatorio: true, slugDe: 'slug'),
                CamposTraducibles::urls(Pack::class, 160),
                CamposTraducibles::texto('descripcion', 'Descripción', largo: true),
                Grid::make(3)->schema([
                    CampoDinero::make('precio', 'Precio cerrado (sin IVA)')->required()->live(onBlur: true)
                        ->helperText(fn (Get $get) => filled($get('precio'))
                            ? 'Con IVA: '.CampoDinero::formatear(Iva::conIva(CampoDinero::aCentimos($get('precio')), Impuestos::tipoIva(filled($get('clase_impuesto_id')) ? (int) $get('clase_impuesto_id') : null)))
                            : 'Incluye las prendas y la impresión de las zonas incluidas.'),
                    Select::make('clase_impuesto_id')->label('Clase de impuesto')->native(false)->live()
                        ->options(fn () => ClaseImpuesto::orderBy('orden')->get()->pluck('nombre', 'id'))
                        ->placeholder('La de por defecto'),
                    ToggleButtons::make('reparto')->label('Tallas y color')->options(RepartoPack::class)->inline()->required()->live()->default(RepartoPack::Fijo),
                ]),
                // D219: cómo se ve el precio en la tienda. En neto, debajo sale siempre el precio final con IVA.
                ToggleButtons::make('precio_neto')->label('Cómo se muestra el precio')->inline()->default(false)
                    ->options([0 => 'IVA incluido', 1 => 'Neto + IVA'])
                    ->formatStateUsing(fn ($state) => (int) (bool) $state)->dehydrateStateUsing(fn ($state) => (bool) $state)
                    ->helperText('«Neto + IVA» enseña «300,00 € + IVA» y, debajo, el precio final con IVA (obligatorio para consumidores).'),
                // D223: cada prenda se imprime solo en las zonas del pack que ofrece; aquí se ve el reparto.
                Select::make('zonas')->label('Impresión incluida')->relationship('zonas', 'id')->multiple()->preload()->required()->live()
                    ->getOptionLabelFromRecordUsing(fn (ZonaImpresion $record) => $record->nombre)
                    ->helperText(fn (Get $get) => self::resumenZonas($get))
                    ->rule(fn (Get $get) => function (string $atributo, $valor, Closure $fallo) use ($get): void {
                        foreach ($get('lineas') ?? [] as $linea) {
                            if (filled($linea['prenda_id'] ?? null) && ZonasPack::dePrenda(array_map('intval', $valor ?? []), (int) $linea['prenda_id']) === []) {
                                $fallo((Prenda::find($linea['prenda_id'])->nombre ?? 'Una prenda').' no ofrece ninguna de estas zonas: no llevaría nada impreso.');

                                return;
                            }
                        }
                    }),
            ]),
            Section::make('Prendas del pack')->schema([
                Repeater::make('lineas')->hiddenLabel()->relationship('lineas')->minItems(1)->defaultItems(1)->columns(3)
                    ->addActionLabel('Añadir prenda')
                    // Reparto en la base de datos: {talla_id: unidades}; en el formulario, filas talla + unidades.
                    ->mutateRelationshipDataBeforeFillUsing(fn (array $data) => [...$data, 'reparto_tallas' => self::filasReparto($data['reparto_tallas'] ?? null)])
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => self::guardarReparto($data))
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => self::guardarReparto($data))
                    ->schema([
                        Select::make('prenda_id')->label('Prenda')->required()->native(false)->live()
                            ->options(fn () => Prenda::where('activa', true)->orderBy('id')->get()->pluck('nombre', 'id')),
                        TextInput::make('unidades')->label('Unidades')->integer()->minValue(1)->required()
                            ->rule(fn (Get $get) => function (string $atributo, $valor, Closure $fallo) use ($get): void {
                                if (self::esFijo($get, '../../reparto')) {
                                    $repartidas = array_sum(array_map(fn ($f) => (int) ($f['unidades'] ?? 0), $get('reparto_tallas') ?? []));
                                    if ($repartidas !== (int) $valor) {
                                        $fallo("El reparto de tallas suma {$repartidas}, no {$valor}.");
                                    }
                                }
                            }),
                        Select::make('colores')->label(fn (Get $get) => self::esFijo($get, '../../reparto') ? 'Color' : 'Colores que puede elegir')
                            ->multiple()->required()->native(false)
                            ->maxItems(fn (Get $get) => self::esFijo($get, '../../reparto') ? 1 : null)
                            ->options(fn (Get $get) => Color::whereIn('id', Variante::where('prenda_id', $get('prenda_id'))->aLaVenta()->pluck('color_id'))->orderBy('orden')->get()->pluck('nombre', 'id'))
                            ->dehydrateStateUsing(fn ($state) => array_values(array_map('intval', $state ?? []))),
                        Repeater::make('reparto_tallas')->label('Reparto de tallas')->columnSpanFull()->columns(2)->defaultItems(1)
                            ->visible(fn (Get $get) => self::esFijo($get, '../../reparto'))
                            ->addActionLabel('Añadir talla')
                            ->schema([
                                Select::make('talla_id')->label('Talla')->required()->native(false)->distinct()
                                    ->options(fn () => Talla::orderBy('orden')->pluck('nombre', 'id')),
                                TextInput::make('unidades')->label('Unidades')->integer()->minValue(1)->required(),
                            ]),
                        Select::make('tallas_permitidas')->label('Tallas que puede elegir')->multiple()->native(false)->columnSpanFull()
                            ->visible(fn (Get $get) => ! self::esFijo($get, '../../reparto'))
                            ->required(fn (Get $get) => ! self::esFijo($get, '../../reparto'))
                            ->options(fn () => Talla::orderBy('orden')->pluck('nombre', 'id'))
                            ->dehydrateStateUsing(fn ($state) => array_values(array_map('intval', $state ?? []))),
                    ]),
            ]),
            // D217: la imagen de la tarjeta del pack. Sin imagen, la foto de su primera prenda.
            Section::make('Imagen del pack')->columns(2)->schema([
                Select::make('imagen_recurso')->label('Imagen de recurso')->native(false)->allowHtml()
                    ->options(collect(Pack::IMAGENES_RECURSO)->mapWithKeys(fn (string $nombre, string $clave) => [$clave => '<span style="display:flex;align-items:center;gap:10px;"><img src="'.e(asset('img/packs/'.$clave.'.webp')).'" alt="" style="width:40px;height:48px;object-fit:cover;border-radius:4px;">'.e($nombre).'</span>'])->all())
                    ->placeholder('Ninguna: la foto de su primera prenda')
                    ->helperText('Cinco imágenes preparadas con la marca. Si subes una propia, se usa la tuya.'),
                FileUpload::make('imagen')->label('O sube la tuya')->image()->disk(Pack::DISCO_IMAGEN)->visibility('public')->directory('packs')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(5120)
                    ->helperText('Vertical 5:6 (por ejemplo, 1000 × 1200 px). PNG, JPG o WebP de hasta 5 MB.'),
            ]),
            Section::make('Publicación')->columns(3)->schema([
                DateTimePicker::make('desde')->label('Desde')->seconds(false),
                DateTimePicker::make('hasta')->label('Hasta')->seconds(false)->after('desde'),
                Toggle::make('activo')->label('Activo')->default(true)->inline(false),
                // D203: en qué «Elige tu modo» de la portada sale. Sin marcar: genérico, sale en todos.
                CheckboxList::make('modos')->label('Para qué modos')->options(ModoCliente::opciones())->columns(3)->columnSpanFull()
                    ->helperText('Sale en la página de cada modo marcado. Si no marcas ninguno, es genérico y sale en todos.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('orden')->reorderable('orden')
            ->description('Precio cerrado: no se combina con cupones ni ofertas y cumple por sí solo el pedido mínimo.')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['lineas', 'zonas']))
            ->columns([
                TextColumn::make('nombre')->label('Pack')->searchable(query: BusquedaTraducible::en('nombre')),
                TextColumn::make('unidades')->label('Prendas')->state(fn (Pack $record) => $record->unidades()),
                TextColumn::make('precio')->label('Precio con IVA')->formatStateUsing(fn ($state, Pack $record) => CampoDinero::formatear(Iva::conIva((int) $state, Impuestos::tipoIva($record->clase_impuesto_id)))),
                TextColumn::make('reparto')->label('Tallas')->badge(),
                TextColumn::make('hasta')->label('Hasta')->dateTime('d/m/Y')->placeholder('Sin fin'),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([static::accionEditar()->slideOver(), static::accionBorrar()->modalDescription('El pack va a la papelera y deja de venderse.'), RestoreAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePacks::route('/')];
    }
}
