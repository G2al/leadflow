<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cliente')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('first_name')->label('Nome'),
                        TextEntry::make('last_name')->label('Cognome'),
                        TextEntry::make('phone')->label('Telefono'),
                        TextEntry::make('address')->label('Indirizzo')->placeholder('Non indicato'),
                    ]),
                Section::make('Ordine')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')->label('Stato')->badge(),
                        TextEntry::make('offer.name')->label('Offerta'),
                        TextEntry::make('offer.product.name')->label('Prodotto'),
                        TextEntry::make('quantity')->label('Quantità')->placeholder('Da definire'),
                        TextEntry::make('unit_price')->label('Prezzo unitario')->money('EUR', locale: 'it'),
                        TextEntry::make('total_price')->label('Totale')->money('EUR', locale: 'it')->placeholder('Da definire'),
                        TextEntry::make('created_at')->label('Ricevuto il')->dateTime('d/m/Y H:i'),
                        TextEntry::make('assignee.full_name')
                            ->label('Assegnato a')
                            ->placeholder('Non assegnato')
                            ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
                    ]),
                Section::make('Origine')
                    ->schema([
                        TextEntry::make('source_url')
                            ->label('Pagina di provenienza')
                            ->placeholder('Origine non disponibile')
                            ->url(fn (Order $record): ?string => $record->source_url, shouldOpenInNewTab: true),
                    ]),
            ]);
    }
}
