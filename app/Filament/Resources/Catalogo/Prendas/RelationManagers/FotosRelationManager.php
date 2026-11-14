<?php

namespace App\Filament\Resources\Catalogo\Prendas\RelationManagers;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\CopiarZonas;
use App\Dominio\Catalogo\IgualarEncuadre;
use App\Dominio\Catalogo\TenirFoto;
use App\Dominio\Catalogo\TipoFoto;
use App\Dominio\Catalogo\ZonasPorDefecto;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Concerns\CamposTraducibles;
use App\Models\Color;
use App\Models\Foto;
use App\Models\Prenda;
use App\Models\ZonaImpresion;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HtmlString;
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
                ->helperText('PNG, JPG o WebP de hasta 5 MB. Al guardar se pasa a WebP y se reduce al tamaño de Ajustes → General, sin perder la transparencia. Maqueta: proporción 5:6 (por ejemplo, 1000 × 1200 px), mejor con fondo transparente y la prenda centrada. La maqueta de cada color se iguala sola al tamaño y encuadre de la del blanco (D239).'),
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
                // D239: todas las maquetas con la prenda al tamaño y encuadre de la del blanco.
                Action::make('igualar')->label('Igualar tamaño al blanco')->icon(Heroicon::OutlinedArrowsPointingIn)->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Las maquetas de los demás colores se rehacen con la prenda al mismo tamaño y en el mismo sitio que en la del blanco (de cada cara), y toman sus zonas de impresión. Funciona con fondo transparente o liso.')
                    ->action(function (): void {
                        $prenda = $this->getOwnerRecord();
                        if (! $prenda instanceof Prenda) {
                            return;
                        }
                        $r = IgualarEncuadre::prenda($prenda);
                        Actividad::apuntar('catalogo', 'Fotos igualadas al blanco', $prenda, ['igualadas' => $r['igualadas']]);
                        $aviso = Notification::make()->{$r['fallidas'] === [] ? 'success' : 'warning'}()
                            ->title($r['igualadas'] === 1 ? '1 foto igualada al blanco' : $r['igualadas'].' fotos igualadas al blanco')
                            ->body(trim(($r['iguales'] ? $r['iguales'].' ya estaban iguales. ' : '').($r['fallidas'] ? 'Sin igualar: '.implode('; ', $r['fallidas']).'.' : '')) ?: null);
                        if ($r['fallidas'] !== []) {
                            $aviso->persistent();
                        }
                        $aviso->send();
                    })
                    ->visible(fn () => Foto::where('prenda_id', $this->getOwnerRecord()->getKey())->where('tipo', TipoFoto::Maqueta->value)->count() > 1),
                // D251: maquetas de otros colores tiñendo la del blanco (sin subir fotos).
                Action::make('tenir')->label('Crear colores desde la blanca')->icon(Heroicon::OutlinedSwatch)->color('gray')
                    ->modalHeading('Crear colores desde la foto blanca')
                    ->modalDescription('Se tiñe la maqueta blanca (delantera y trasera) con el color de la ficha de cada color. La prenda queda idéntica y toma las zonas de impresión del blanco. Las fotos reales que ya tengas no se tocan. Necesita la foto blanca con fondo transparente.')
                    ->modalSubmitActionLabel('Crear las fotos')
                    ->schema([
                        CheckboxList::make('colores')->label('Colores')->required()->columns(3)->live()
                            ->options(fn () => self::coloresParaTenir($this->getOwnerRecord()))
                            ->descriptions(fn () => self::fotosReales($this->getOwnerRecord()))
                            ->disableOptionWhen(fn (string $value) => array_key_exists((int) $value, self::fotosReales($this->getOwnerRecord()))),
                        Placeholder::make('vista')->label('Vista previa (delantera)')
                            ->content(fn (Get $get) => self::vistaTenida($this->getOwnerRecord(), array_map('intval', (array) $get('colores')))),
                    ])
                    ->action(function (array $data): void {
                        $prenda = $this->getOwnerRecord();
                        if (! $prenda instanceof Prenda) {
                            return;
                        }
                        $r = TenirFoto::colores($prenda, array_map('intval', (array) ($data['colores'] ?? [])));
                        Actividad::apuntar('catalogo', 'Colores creados desde la foto blanca', $prenda, ['creadas' => $r['creadas'], 'rehechas' => $r['rehechas']]);
                        $hechas = $r['creadas'] + $r['rehechas'];
                        $avisos = array_filter([
                            $r['con_foto_real'] ? 'Con foto real (no se tocan): '.implode(', ', $r['con_foto_real']).'.' : null,
                            $r['sin_base'] ? 'Sin maqueta blanca en: '.implode(', ', $r['sin_base']).'.' : null,
                            $r['sin_transparencia'] ? 'La foto blanca no tiene fondo transparente en: '.implode(', ', $r['sin_transparencia']).'.' : null,
                        ]);
                        $aviso = Notification::make()->{$avisos === [] ? 'success' : 'warning'}()
                            ->title($hechas === 1 ? '1 foto creada' : $hechas.' fotos creadas')->body(implode(' ', $avisos) ?: null);
                        if ($avisos !== []) {
                            $aviso->persistent();
                        }
                        $aviso->send();
                    })
                    ->visible(fn () => $this->getOwnerRecord() instanceof Prenda && TenirFoto::base($this->getOwnerRecord(), Cara::Delantera) !== null),
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

    /**
     * D251: los colores de la prenda (de sus variantes) que no son el de la foto blanca.
     *
     * @return array<int, string>
     */
    public static function coloresParaTenir(Model $prenda): array
    {
        if (! $prenda instanceof Prenda) {
            return [];
        }
        $base = TenirFoto::base($prenda, Cara::Delantera);

        return Color::whereIn('id', $prenda->variantes()->pluck('color_id')->unique())->whereKeyNot((int) $base?->color_id)
            ->orderBy('orden')->get()->mapWithKeys(fn ($c) => [$c->getKey() => (string) $c->getTranslation('nombre', 'es')])->all();
    }

    /** @return array<int, string> colores que ya tienen una foto real (no se tiñen) */
    public static function fotosReales(Model $prenda): array
    {
        return Foto::where('prenda_id', $prenda->getKey())->where('tipo', TipoFoto::Maqueta->value)->whereNull('tenida_desde_id')
            ->whereNotNull('color_id')->pluck('color_id')->unique()->mapWithKeys(fn ($id) => [(int) $id => 'Ya tiene foto real'])->all();
    }

    /** @param  list<int>  $colores */
    public static function vistaTenida(Model $prenda, array $colores): HtmlString
    {
        $base = $prenda instanceof Prenda ? TenirFoto::base($prenda, Cara::Delantera) : null;
        if ($base === null || $colores === []) {
            return new HtmlString('<span style="opacity:.7">Marca uno o más colores para verlos.</span>');
        }
        if (! TenirFoto::transparente($base)) {
            return new HtmlString('<span style="color:#d97706">La foto blanca no tiene fondo transparente: súbela en PNG con el fondo transparente para poder teñirla.</span>');
        }
        $html = Color::whereIn('id', array_slice($colores, 0, 8))->get()->map(function ($c) use ($base) {
            $img = 'data:image/webp;base64,'.base64_encode(TenirFoto::imagen($base, (string) $c->codigo_hex, 220));

            return '<figure style="margin:0;text-align:center"><img src="'.$img.'" alt="" style="width:120px;border-radius:10px;background:#f1f1f3"><figcaption style="font-size:12px;margin-top:4px">'.e((string) $c->getTranslation('nombre', 'es')).'</figcaption></figure>';
        })->implode('');

        return new HtmlString('<div style="display:flex;flex-wrap:wrap;gap:12px">'.$html.'</div>');
    }
}
