<?php

namespace App\Filament\Resources\Precios;

use App\Dominio\Precios\Formato;
use App\Dominio\Precios\MetodoEnvio;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\TarifaEnvio;
use App\Models\Transportista;
use App\Models\ZonaEnvio;
use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/** Ajustes → Transportistas (D226): ficha de cada empresa de transporte, con las zonas y tarifas que lleva. */
class TransportistaResource extends RecursoPrecios
{
    protected static ?string $model = Transportista::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'Transportistas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'transportista';

    protected static ?string $pluralModelLabel = 'transportistas';

    protected static ?string $slug = 'transportistas';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Ficha')->schema([
                Grid::make(2)->schema([
                    TextInput::make('nombre')->label('Empresa')->required()->maxLength(80)->unique(ignoreRecord: true),
                    TextInput::make('web')->label('Web')->url()->maxLength(200),
                    TextInput::make('contacto')->label('Persona de contacto')->maxLength(120),
                    TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                    TextInput::make('correo')->label('Correo')->email()->maxLength(150),
                    TextInput::make('numero_cliente')->label('Número de cliente o cuenta')->maxLength(60),
                ]),
                TextInput::make('url_seguimiento')->label('Enlace de seguimiento')->maxLength(300)
                    ->placeholder('https://www.transportista.es/seguimiento?envio={numero}')
                    ->rule(fn () => function (string $atributo, $valor, \Closure $fallo): void {
                        // Las llaves de {numero} no pasan la regla «url»: se comprueba con el número puesto.
                        if (filled($valor) && (! str_contains((string) $valor, '{numero}') || filter_var(str_replace('{numero}', '123', (string) $valor), FILTER_VALIDATE_URL) === false)) {
                            $fallo('Pon una dirección web con {numero} donde va el número de seguimiento.');
                        }
                    })
                    ->helperText('Con {numero} donde va el número de seguimiento. Al marcar un pedido como enviado, el cliente recibe este enlace.'),
                Textarea::make('notas')->label('Notas internas')->rows(3)->maxLength(2000),
                Toggle::make('activo')->label('Activo')->default(true),
            ]),
            Section::make('Zonas y tarifas')->description('Las tarifas de cada zona se editan en Ajustes → Envío, donde también eliges qué transportista la lleva.')
                ->visible(fn (?Transportista $record) => $record !== null)
                ->schema([
                    Placeholder::make('tarifas')->hiddenLabel()->content(fn (?Transportista $record) => self::tarifas($record)),
                ]),
        ]);
    }

    /** Resumen de las zonas que lleva y sus tarifas. */
    public static function tarifas(?Transportista $transportista): HtmlString
    {
        $zonas = $transportista === null ? collect() : ZonaEnvio::with('tarifas')->where('transportista_id', $transportista->id)->orderByDesc('prioridad')->get();
        if ($zonas->isEmpty()) {
            return new HtmlString('Todavía no lleva ninguna zona.');
        }

        return new HtmlString($zonas->map(function (ZonaEnvio $z): string {
            $donde = implode(', ', $z->paises ?? []).($z->prefijos_cp ? ' · CP '.implode(', ', $z->prefijos_cp) : '');
            $precio = $z->metodo === MetodoEnvio::PorCaja
                ? ($z->precio_caja === null ? 'sin precio por caja' : Formato::euros($z->precio_caja).' por caja')
                : ($z->tarifas->isEmpty() ? 'sin tarifas' : $z->tarifas->map(fn (TarifaEnvio $t) => 'hasta '.rtrim(rtrim(number_format($t->hasta_gramos / 1000, 2, ',', ''), '0'), ',').' kg: '.Formato::euros($t->precio))->implode(' · ')
                    .($z->precio_kg_extra !== null ? ' · kilo extra: '.Formato::euros($z->precio_kg_extra) : ''));

            return '<p style="margin:0 0 .5rem"><strong>'.e($z->nombre).'</strong>'.($z->activa ? '' : ' <em>(inactiva)</em>').' · '.e($donde).' · '.e($z->plazo_dias.' días').'<br><span style="opacity:.8">'.e($precio).' (sin IVA)</span></p>';
        })->implode(''));
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('nombre')
            ->description('Cada zona de envío dice qué transportista la lleva; sus tarifas son las de la zona.')
            ->columns([
                TextColumn::make('nombre')->label('Transportista')->searchable(),
                TextColumn::make('zonas_count')->label('Zonas')->counts('zonas'),
                TextColumn::make('contacto')->label('Contacto')->description(fn (Transportista $r) => $r->telefono)->placeholder('—'),
                IconColumn::make('url_seguimiento')->label('Seguimiento')->boolean()->state(fn (Transportista $r) => filled($r->url_seguimiento)),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->recordActions([static::accionEditar()->slideOver(), static::accionBorrar()->modalDescription('Las zonas que llevaba se quedan sin transportista asignado.')]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTransportistas::route('/')];
    }
}
