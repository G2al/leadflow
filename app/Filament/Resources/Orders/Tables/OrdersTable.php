<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderActions;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        $isAdmin = fn (): bool => auth()->user()?->isAdmin() ?? false;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('offer')
                ->when($isAdmin(), fn (Builder $query): Builder => $query->with('assignee')))
            ->defaultSort('created_at', 'desc')
            ->poll('15s')
            ->stackedOnMobile()
            ->columns([
                TextColumn::make('id')
                    ->label('Ordine')
                    ->formatStateUsing(fn (int $state): string => "#{$state}")
                    ->weight('medium')
                    ->description(fn (Order $record): string => $record->created_at
                        ->timezone(config('leadflow.display_timezone'))
                        ->format('d/m/Y H:i'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->wrap()
                    // Under the badge, so the assignee never becomes a column operators could see.
                    ->description(fn (Order $record): ?string => $isAdmin() ? $record->assignee?->full_name : null)
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->wrap()
                    ->weight('medium')
                    ->description(fn (Order $record): string => $record->phone)
                    ->searchable(['first_name', 'last_name', 'phone'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('offer.name')
                    ->label('Offerta')
                    ->wrap()
                    ->description(fn (Order $record): string => $record->source_host ?? 'Origine n.d.')
                    ->searchable(),
                TextColumn::make('unit_price')
                    ->label('Prezzo')
                    ->money('EUR', locale: 'it')
                    ->description(fn (Order $record): string => self::amountSummary($record))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(OrderStatus::class),
                SelectFilter::make('offer')
                    ->label('Offerta')
                    ->relationship('offer', 'name'),
                Filter::make('mine')
                    ->label('I miei ordini')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('assigned_to', auth()->id())),
                SelectFilter::make('assigned_to')
                    ->label('Assegnato a')
                    ->options(fn () => User::query()->assignable()->orderBy('name')->get()->pluck('full_name', 'id'))
                    ->visible($isAdmin),
            ])
            ->recordActions([
                // At most one of these is visible: the next step for the order.
                OrderActions::claim()->button(),
                OrderActions::confirm()->button(),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()->label('Modifica dati'),
                    OrderActions::cancel(),
                    OrderActions::assign(),
                ]),
            ]);
    }

    private static function amountSummary(Order $order): string
    {
        if ($order->quantity === null) {
            return 'Q.tà da definire';
        }

        return "× {$order->quantity} = ".Number::currency((float) $order->total_price, 'EUR', 'it');
    }
}
