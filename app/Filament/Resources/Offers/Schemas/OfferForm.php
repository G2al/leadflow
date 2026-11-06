<?php

namespace App\Filament\Resources\Offers\Schemas;

use App\Models\Offer;
use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati offerta')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome offerta')
                            ->required()
                            ->maxLength(255),
                        Select::make('product_id')
                            ->label('Prodotto')
                            ->relationship(
                                'product',
                                'name',
                                // An existing offer keeps its product even if it was deactivated later.
                                modifyQueryUsing: fn (Builder $query, ?Offer $record): Builder => $query
                                    ->where(fn (Builder $query): Builder => $query
                                        ->where('is_active', true)
                                        ->when($record, fn (Builder $query): Builder => $query->orWhere('id', $record->product_id))),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        TextInput::make('custom_price')
                            ->label('Prezzo personalizzato')
                            ->helperText('Lascia vuoto per usare il prezzo di vendita del prodotto.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(Product::MAX_PRICE)
                            ->step(0.01)
                            ->prefix('€')
                            ->live(onBlur: true),
                        TextEntry::make('effective_price_preview')
                            ->label('Prezzo effettivo')
                            ->state(fn (Get $get): ?string => self::effectivePrice($get('custom_price'), $get('product_id')))
                            ->money('EUR', locale: 'it')
                            ->placeholder('Seleziona un prodotto'),
                        Toggle::make('is_active')
                            ->label('Attiva')
                            ->default(true)
                            ->helperText('Se disattivata, il modulo sparisce dalle landing e gli invii vengono rifiutati.'),
                    ]),
            ]);
    }

    private static function effectivePrice(mixed $customPrice, mixed $productId): ?string
    {
        if (is_numeric($customPrice)) {
            return (string) $customPrice;
        }

        return Product::query()->whereKey($productId)->value('selling_price');
    }
}
