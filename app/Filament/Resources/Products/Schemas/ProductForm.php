<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati prodotto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(4)
                            ->columnSpanFull(),
                        FileUpload::make('image')
                            ->label('Foto')
                            ->image()
                            ->disk('public')
                            ->directory('products')
                            ->visibility('public')
                            ->maxSize(4096)
                            ->columnSpanFull(),
                    ]),
                Section::make('Prezzi e stato')
                    ->columns(2)
                    ->schema([
                        self::priceInput('purchase_price', 'Prezzo di acquisto'),
                        self::priceInput('selling_price', 'Prezzo di vendita'),
                        Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true)
                            ->helperText('Un prodotto disattivato non riceve richieste e conserva il suo storico.'),
                    ]),
            ]);
    }

    private static function priceInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->required()
            ->numeric()
            ->minValue(0)
            ->maxValue(Product::MAX_PRICE)
            ->step(0.01)
            ->prefix('€');
    }
}
