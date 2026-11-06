<?php

namespace App\Filament\Resources\Offers\Tables;

use App\Models\Offer;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Offers are browsed as product-style cards rather than table rows.
 */
class OffersTable
{
    /** Neutral picture for products without a photo. */
    private const IMAGE_PLACEHOLDER = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' fill='%23f3f4f6'/%3E%3Cpath d='M32 18 46 26v14l-14 8-14-8V26z' fill='none' stroke='%239ca3af' stroke-width='2' stroke-linejoin='round'/%3E%3Cpath d='M18 26l14 8 14-8M32 34v14' fill='none' stroke='%239ca3af' stroke-width='2' stroke-linejoin='round'/%3E%3C/svg%3E";

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('product'))
            ->defaultSort('created_at', 'desc')
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->paginated([12, 24, 48])
            ->defaultPaginationPageOption(12)
            ->columns([
                Stack::make([
                    ImageColumn::make('product.image')
                        ->disk('public')
                        ->visibility('public')
                        ->defaultImageUrl(self::IMAGE_PLACEHOLDER)
                        ->imageWidth('100%')
                        ->imageHeight(180)
                        ->extraImgAttributes(['style' => 'object-fit: cover; border-radius: 0.5rem;']),
                    Stack::make([
                        TextColumn::make('name')
                            ->label('Offerta')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->searchable()
                            ->sortable(),
                        TextColumn::make('product.name')
                            ->label('Prodotto')
                            ->color('gray')
                            ->searchable(),
                    ])->space(1),
                    Split::make([
                        TextColumn::make('effective_price')
                            ->label('Prezzo effettivo')
                            ->state(fn (Offer $record): string => $record->effectivePrice())
                            ->money('EUR', locale: 'it')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->description(fn (Offer $record): string => $record->custom_price === null
                                ? 'Prezzo del prodotto'
                                : 'Prezzo personalizzato'),
                        TextColumn::make('availability')
                            ->label('Stato')
                            ->badge()
                            ->state(fn (Offer $record): string => self::availability($record))
                            ->color(fn (Offer $record): string => $record->acceptsRequests() ? 'success' : 'danger')
                            ->grow(false),
                    ]),
                ])->space(3),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Stato attivo'),
                SelectFilter::make('product')
                    ->label('Prodotto')
                    ->relationship('product', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    private static function availability(Offer $offer): string
    {
        return match (true) {
            ! $offer->is_active => 'Disattivata',
            ! $offer->product->is_active => 'Prodotto disattivato',
            default => 'Attiva',
        };
    }
}
