<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Support\OrderRules;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        $rules = OrderRules::customer();

        return $schema
            ->components([
                Section::make('Dati cliente')
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Nome')
                            ->required()
                            ->rules($rules['first_name']),
                        TextInput::make('last_name')
                            ->label('Cognome')
                            ->required()
                            ->rules($rules['last_name']),
                        TextInput::make('phone')
                            ->label('Telefono')
                            ->tel()
                            ->required()
                            ->rules($rules['phone']),
                        self::addressInput()
                            ->helperText("Obbligatorio per confermare l'ordine.")
                            ->columnSpanFull(),
                    ]),
                Section::make('Ordine')
                    ->columns(3)
                    ->schema([
                        self::quantityInput()
                            ->helperText("Obbligatoria per confermare l'ordine."),
                        self::unitPriceEntry(),
                        self::totalEntry(),
                    ]),
            ]);
    }

    public static function addressInput(): Textarea
    {
        return Textarea::make('address')
            ->label('Indirizzo')
            ->rows(3)
            ->rules(OrderRules::customer()['address']);
    }

    public static function quantityInput(): TextInput
    {
        return TextInput::make('quantity')
            ->label('Quantità')
            ->integer()
            ->minValue(1)
            ->maxValue(Order::MAX_QUANTITY)
            ->rules(OrderRules::quantity())
            ->live(debounce: 300);
    }

    public static function unitPriceEntry(): TextEntry
    {
        return TextEntry::make('unit_price')
            ->label('Prezzo unitario')
            ->state(fn (Order $record): string => $record->unit_price)
            ->money('EUR', locale: 'it');
    }

    public static function totalEntry(): TextEntry
    {
        return TextEntry::make('total_preview')
            ->label('Totale')
            ->state(fn (Order $record, Get $get): ?string => self::totalPreview($record, $get('quantity')))
            ->money('EUR', locale: 'it')
            ->placeholder('Inserisci la quantità');
    }

    private static function totalPreview(Order $order, mixed $quantity): ?string
    {
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

        return $quantity !== false && $quantity >= 1 && $quantity <= Order::MAX_QUANTITY
            ? $order->totalFor($quantity)
            : null;
    }
}
