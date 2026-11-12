<?php

namespace App\Filament\Resources\Catalogo\Prendas\RelationManagers;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\CopiarZonas;
use App\Dominio\Catalogo\TipoFoto;
use App\Dominio\Catalogo\ZonasPorDefecto;
use App\Filament\Concerns\CamposTraducibles;
use App\Models\Color;
use App\Models\Foto;
use App\Models\ZonaImpresion;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\Unique;

/**
 * Fotos de la prenda. Maqueta: foto plana por color y cara, con las zonas
 * de impresión marcadas encima. Galería: con modelo o de detalle (D30).
 */
class FotosRelationManager extends RelationManager
{
    protected static string $relationship = 'fotos';

    protected static ?string $title = 'Fotos';

    /** D211: ¿copiar las zonas editadas a los demás colores? (casilla del formulario, no es un campo de la foto) */
    public bool $copiarZonas = false;

    protected static ?string $modelLabel = 'foto';

    protected static ?string $pluralModelLabel = 'fotos';

    public function form(Schema $schema): Schema
    {
        $esMaqueta = fn (Get $get): bool => $get('tipo') === TipoFoto::Maqueta->value || $get('tipo') === TipoFoto::Maqueta;
        $porcentaje = fn (string $campo, string $etiqueta) => TextInput::make($campo)->label($etiqueta)->numeric()->minValue(0)->maxValue(100)->step(0.1)->suffix('%')->required()->live(onBlur: true)
            ->formatStateUsing(fn ($state) => $state === null ? null : $state / 100)
            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100));

        // La zona no puede salirse de la foto (posición + tamaño ≤ 100 %).
        $dentro = fn (string $posicion, string $etiqueta) => fn (Get $get) => function (string $atributo, $valor, Closure $fallo) use ($get, $posicion, $etiqueta): void {
            // En centésimas, igual que se guarda: sin escapes por redondeo.
            if ((int) round(((float) $get($posicion)) * 100) + (int) round(((float) $valor) * 100) > 10000) {
                $fallo("La zona se sale de la foto: «{$etiqueta}» más este valor no puede pasar del 100 %.");
            }
        };

        // El rectángulo tiene la proporción de la zona en mm, sobre una foto 5:6 (Gauntlet de la
        // fase 5, M2): si no, el visor y la maqueta del carrito muestran tamaños distintos.
        $proporcionada = fn (Get $get) => function (string $atributo, $valor, Closure $fallo) use ($get): void {
            $zona = ZonaImpresion::find($get('zona_impresion_id'));
            $ancho = (float) $get('ancho');
            if ($zona === null || $ancho <= 0 || (float) $valor <= 0) {
                return;
            }
            $enFoto = ($ancho * 5) / ((float) $valor * 6);
            $real = $zona->ancho_max_mm / max(1, $zona->alto_max_mm);
            if (abs($enFoto / $real - 1) > 0.05) {
                $alto = round($ancho * 5 / ($real * 6), 1);
                $fallo("El rectángulo no tiene la proporción de la zona ({$zona->ancho_max_mm} × {$zona->alto_max_mm} mm). Con ese ancho, el alto debería ser de unos {$alto} %.");
            }
        };

        return $schema->columns(1)->components([
            Grid::make(3)->schema([
                Select::make('tipo')->label('Tipo')->options(TipoFoto::class)->default(TipoFoto::Maqueta)->required()->native(false)->live(),
                Select::make('color_id')->label('Color')->native(false)
                    ->options(fn () => Color::orderBy('orden')->get()->pluck('nombre', 'id'))
                    ->required($esMaqueta)
                    ->helperText(fn (Get $get) => $esMaqueta($get) ? null : 'Opcional: si la foto es de un color concreto.'),
                Select::make('cara')->label('Cara')->options(Cara::class)->native(false)
                    ->required($esMaqueta)->visible($esMaqueta)
                    ->unique(table: 'fotos', column: 'cara', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                        ->where('prenda_id', $this->getOwnerRecord()->getKey())
                        ->where('tipo', TipoFoto::Maqueta->value)
                        ->where('color_id', $get('color_id')))
                    ->validationMessages(['unique' => 'Ya hay una maqueta de este color y cara. Edítala para cambiar la foto.']),
            ]),
            FileUpload::make('ruta')->label('Foto')->required()
                ->image()->disk(Foto::DISCO)->visibility('public')
                ->directory(fn () => 'catalogo/'.$this->getOwnerRecord()->getKey())
                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                ->maxSize(5120)
                // Una maqueta tiene que ser 5:6 (con un 2 % de margen): el visor y las zonas lo suponen (Gauntlet fase 5, B2).
                ->rule(fn (Get $get) => function (string $atributo, $valor, Closure $fallo) use ($get, $esMaqueta): void {
                    if (! $esMaqueta($get)) {
                        return;
                    }
                    // El estado de la subida es una lista de archivos; al editar, la foto guardada es un texto.
                    foreach (is_array($valor) ? $valor : [$valor] as $archivo) {
                        if (! $archivo instanceof UploadedFile) {
                            continue;
                        }
                        [$ancho, $alto] = @getimagesize($archivo->getRealPath()) ?: [0, 0];
                        if ($alto > 0 && abs(($ancho / $alto) / (5 / 6) - 1) > 0.02) {
                            $fallo("Una maqueta tiene que tener proporción 5:6 (por ejemplo, 1000 × 1200 px). Esta foto es de {$ancho} × {$alto} px.");
                        }
                    }
                })
                ->helperText('PNG, JPG o WebP de hasta 5 MB. Al guardar se pasa a WebP y se reduce al tamaño de Ajustes → General, sin perder la transparencia. Maqueta: proporción 5:6 (por ejemplo, 1000 × 1200 px), mejor con fondo transparente y la prenda centrada.'),
            CamposTraducibles::texto('texto_alternativo', 'Texto alternativo'),
            TextInput::make('orden')->label('Orden')->numeric()->integer()->minValue(0)->default(0),

            ToggleButtons::make('ejemplo')->label('Diseño de ejemplo')
                ->options(['cuadrado' => 'Logo cuadrado', 'horizontal' => 'Texto horizontal', 'vertical' => 'Cartel vertical'])
                ->default('cuadrado')->inline()->live()->dehydrated(false)
                ->afterStateHydrated(fn (ToggleButtons $component, $state) => $component->state($state ?: 'cuadrado'))
                ->helperText('Se coloca como en la tienda: centrado y pegado arriba de la zona. Ajusta las zonas hasta que quede donde se imprime.')
                ->visible(fn (string $operation, ?Foto $record) => $operation === 'edit' && $record?->tipo === TipoFoto::Maqueta),
            ViewField::make('vista_previa')->view('filament.fotos.vista-previa')->dehydrated(false)
                // Con los valores del formulario: se actualiza sin guardar.
                ->viewData(fn (Get $get) => ['zonasFormulario' => $get('zonas') ?? [], 'ejemplo' => $get('ejemplo') ?: 'cuadrado'])
                ->visible(fn (string $operation, ?Foto $record) => $operation === 'edit' && $record?->tipo === TipoFoto::Maqueta),
            Toggle::make('copiar_a_colores')
                ->label(fn (?Foto $record) => 'Aplicar estas posiciones a '.(($n = $record ? count(CopiarZonas::hermanas($record)) : 0) === 1 ? 'la maqueta del otro color' : "las maquetas de los otros {$n} colores").' (misma cara)')
                ->helperText('Las fotos de una prenda suelen tener el mismo encuadre: colocas las zonas una vez. Desmárcalo si este color tiene la foto encuadrada distinta.')
                ->default(true)
                ->visible(fn (string $operation, ?Foto $record) => $operation === 'edit' && $record !== null && CopiarZonas::esMaqueta($record) && CopiarZonas::hermanas($record) !== []),
            Repeater::make('zonas')->label('Zonas de impresión sobre la foto')
                ->relationship('zonas')
                ->defaultItems(0)
                // Solo al editar: al subir la foto, las zonas se colocan solas.
                ->visible(fn (string $operation, ?Foto $record) => $operation === 'edit' && $record?->tipo === TipoFoto::Maqueta)
                ->addable(false)->deletable(false)->reorderable(false)
                ->itemLabel(fn (array $state) => ZonaImpresion::find($state['zona_impresion_id'] ?? null)?->nombre)
                ->columns(4)
                ->schema([
                    $porcentaje('x', 'Desde la izquierda'),
                    $porcentaje('y', 'Desde arriba'),
                    $porcentaje('ancho', 'Ancho')->rule($dentro('x', 'Desde la izquierda')),
                    $porcentaje('alto', 'Alto')->rule($dentro('y', 'Desde arriba'))->rule($proporcionada),
                ])
                ->helperText('En % de la foto. Se rellenan solas al mover las zonas sobre la foto; si las escribes, la foto se actualiza al salir de cada casilla.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            ->emptyStateHeading('Sin fotos')
            ->emptyStateDescription('Sube la maqueta (foto plana) de cada color, delantera y trasera.')
            ->columns([
                ImageColumn::make('ruta')->label('')->disk(Foto::DISCO)->imageHeight(64),
                TextColumn::make('tipo')->label('Tipo')->badge(),
                TextColumn::make('color.nombre')->label('Color')->placeholder('—'),
                TextColumn::make('cara')->label('Cara')->placeholder('—'),
                TextColumn::make('zonas_count')->label('Zonas marcadas')->counts('zonas'),
            ])
            ->headerActions([
                CreateAction::make()->label('Subir foto')
                    ->mutateDataUsing(fn (array $data) => CamposTraducibles::limpiar($data, ['texto_alternativo']))
                    ->after(fn (Foto $record) => app(ZonasPorDefecto::class)($record)),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Model $record) => CamposTraducibles::rellenar($data, $record) + ['copiar_a_colores' => true])
                    ->mutateDataUsing(function (array $data): array {
                        // No es un campo de la foto: se recuerda para después de guardar (D211).
                        $this->copiarZonas = (bool) ($data['copiar_a_colores'] ?? false);
                        unset($data['copiar_a_colores']);

                        return CamposTraducibles::limpiar($data, ['texto_alternativo']);
                    })
                    ->after(function (Foto $record): void {
                        app(ZonasPorDefecto::class)($record);
                        if ($this->copiarZonas && ($n = CopiarZonas::aOtrosColores($record)) > 0) {
                            Notification::make()->success()->title($n === 1 ? 'Zonas copiadas al otro color' : "Zonas copiadas a los otros {$n} colores")->send();
                        }
                    }),
                DeleteAction::make(),
            ]);
    }
}
