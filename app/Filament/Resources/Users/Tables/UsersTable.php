<?php

namespace App\Filament\Resources\Users\Tables;

use App\Dominio\Sistema\Acceso\Rol;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rol')
                    ->label('Rol')
                    ->badge()
                    ->state(fn (User $record): ?string => $record->rol()?->getLabel())
                    ->placeholder('Sin rol'),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('rol')
                    ->label('Rol')
                    ->options(collect(Rol::cases())->mapWithKeys(fn (Rol $rol) => [$rol->value => $rol->getLabel()]))
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->role($data['value'])
                        : $query),
                TernaryFilter::make('activo')
                    ->label('Activo'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
