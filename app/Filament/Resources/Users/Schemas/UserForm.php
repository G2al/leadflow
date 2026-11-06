<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
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
        return $schema
            ->components([
                Section::make('Anagrafica')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('surname')
                            ->label('Cognome')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->label('Telefono')
                            ->tel()
                            ->maxLength(30),
                    ]),
                Section::make('Accesso')
                    ->columns(2)
                    ->schema([
                        Select::make('role')
                            ->label('Ruolo')
                            ->options(UserRole::class)
                            ->required(),
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->rule(Password::defaults())
                            ->maxLength(255)
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Lascia vuoto per non modificarla.' : null),
                        Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true)
                            ->helperText('Un utente disattivato non può accedere; i suoi ordini restano nello storico.'),
                    ]),
            ]);
    }
}
