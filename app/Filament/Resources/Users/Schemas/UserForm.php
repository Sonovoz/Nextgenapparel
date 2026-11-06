<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Dominio\Sistema\Acceso\Rol;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $esUnoMismo = fn (?User $record): bool => $record !== null && $record->is(auth()->user());

        return $schema
            ->components([
                Section::make('Datos')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ]),

                Section::make('Acceso')
                    ->columns(2)
                    ->schema([
                        Select::make('rol')
                            ->label('Rol')
                            ->options(collect(Rol::cases())->mapWithKeys(fn (Rol $rol) => [$rol->value => $rol->getLabel()]))
                            ->required()
                            ->native(false)
                            ->live()
                            ->disabled($esUnoMismo)
                            ->helperText(fn (?string $state): ?string => Rol::tryFrom((string) $state)?->descripcion()),
                        Toggle::make('activo')
                            ->label('Activo')
                            ->helperText('Un usuario desactivado no puede entrar, pero se conserva su historial.')
                            ->default(true)
                            ->disabled($esUnoMismo)
                            ->inline(false),
                    ]),

                Section::make('Contraseña')
                    ->description(fn (string $operation): string => $operation === 'edit'
                        ? 'Déjala en blanco para no cambiarla.'
                        : 'Mínimo 12 caracteres.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->rule(Password::min(12))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->label('Repite la contraseña')
                            ->password()
                            ->revealable()
                            ->requiredWith('password')
                            ->dehydrated(false),
                    ]),
            ]);
    }
}
