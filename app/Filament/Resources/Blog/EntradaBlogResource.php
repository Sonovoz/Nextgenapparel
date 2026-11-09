<?php

namespace App\Filament\Resources\Blog;

use App\Filament\Concerns\CamposTraducibles;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Filament\Resources\Blog\Pages\CreateEntradaBlog;
use App\Filament\Resources\Blog\Pages\EditEntradaBlog;
use App\Filament\Resources\Blog\Pages\ListEntradasBlog;
use App\Models\EntradaBlog;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Marketing → Blog (v2, D195): entradas es/en en Markdown, con SEO y «Proponer SEO con IA». */
class EntradaBlogResource extends Resource
{
    protected static ?string $model = EntradaBlog::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Marketing;

    protected static ?string $navigationLabel = 'Blog';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'entrada';

    protected static ?string $pluralModelLabel = 'entradas';

    protected static ?string $slug = 'blog';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Entrada')->schema([
                CamposTraducibles::texto('titulo', 'Título', obligatorio: true, slugDe: 'slug'),
                CamposTraducibles::urls(EntradaBlog::class, 160),
                CamposTraducibles::texto('extracto', 'Extracto', largo: true),
                CamposTraducibles::texto('contenido', 'Contenido (Markdown)', obligatorio: true, largo: true),
                DateTimePicker::make('publicada_en')->label('Publicar el')->native(false)->helperText('Vacío: borrador. Con fecha futura, se publica ese día.'),
            ]),
            Section::make('SEO')->schema([
                CamposTraducibles::texto('seo_titulo', 'Título SEO'),
                CamposTraducibles::texto('seo_descripcion', 'Metadescripción', largo: true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->emptyStateHeading('Sin entradas')
            ->columns([
                TextColumn::make('titulo')->label('Título')->weight('bold'),
                TextColumn::make('publicada_en')->label('Publicada')->dateTime('d/m/Y H:i')->placeholder('Borrador'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListEntradasBlog::route('/'), 'create' => CreateEntradaBlog::route('/crear'), 'edit' => EditEntradaBlog::route('/{record}/editar')];
    }
}
