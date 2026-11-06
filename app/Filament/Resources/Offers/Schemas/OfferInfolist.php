<?php

namespace App\Filament\Resources\Offers\Schemas;

use App\Models\Offer;
use App\Services\OfferEmbedCode;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

class OfferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dettagli')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Nome offerta'),
                        TextEntry::make('product.name')->label('Prodotto'),
                        TextEntry::make('effective_price')
                            ->label('Prezzo effettivo')
                            ->state(fn (Offer $record): string => $record->effectivePrice())
                            ->money('EUR', locale: 'it'),
                        TextEntry::make('custom_price')
                            ->label('Prezzo personalizzato')
                            ->money('EUR', locale: 'it')
                            ->placeholder('Prezzo del prodotto'),
                        IconEntry::make('is_active')->label('Attiva')->boolean(),
                        TextEntry::make('availability')
                            ->label('Modulo sulle landing')
                            ->badge()
                            ->state(fn (Offer $record): string => $record->acceptsRequests() ? 'Disponibile' : 'Non disponibile')
                            ->color(fn (Offer $record): string => $record->acceptsRequests() ? 'success' : 'danger'),
                    ]),
                Section::make('Codice di incorporamento')
                    ->description('Incolla questo codice nella landing page, nel punto in cui deve comparire il modulo. Lo stesso codice può essere usato su più landing.')
                    ->afterHeader([self::copyScriptAction()])
                    ->schema([
                        TextEntry::make('embed_snippet')
                            ->hiddenLabel()
                            ->state(fn (Offer $record, OfferEmbedCode $embedCode): string => $embedCode->snippet($record))
                            ->fontFamily(FontFamily::Mono)
                            ->copyable()
                            ->copyMessage('Script copiato'),
                    ]),
            ]);
    }

    private static function copyScriptAction(): Action
    {
        return Action::make('copyScript')
            ->label('Copia script')
            ->icon(Heroicon::OutlinedClipboard)
            ->alpineClickHandler(function (Offer $record, OfferEmbedCode $embedCode): string {
                $snippet = Js::from($embedCode->snippet($record));

                return "window.navigator.clipboard.writeText({$snippet}).then(() => new FilamentNotification().title('Script copiato').success().send())";
            });
    }
}
