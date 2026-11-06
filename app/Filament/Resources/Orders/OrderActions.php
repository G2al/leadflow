<?php

namespace App\Filament\Resources\Orders;

use App\Actions\Orders\AssignOrder;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ClaimOrder;
use App\Actions\Orders\ConfirmOrder;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Models\Order;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * UI wiring for order operations, shared by the table and the detail page.
 * Visibility is cosmetic: the domain Actions authorize again under lock.
 */
final class OrderActions
{
    public static function claim(): Action
    {
        return Action::make('claim')
            ->label('Prendi in carico')
            ->icon(Heroicon::OutlinedHandRaised)
            ->color('primary')
            ->visible(fn (Order $record): bool => Gate::allows('claim', $record))
            ->action(fn (Order $record, ClaimOrder $claimOrder) => self::run(
                $record,
                fn () => $claimOrder->handle($record, self::actor()),
                'Ordine preso in carico.',
            ));
    }

    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label('Conferma')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalHeading('Conferma ordine')
            ->modalDescription("Verifica quantità e indirizzo con il cliente. L'ordine confermato non potrà più essere modificato.")
            ->modalSubmitActionLabel('Conferma ordine')
            ->visible(fn (Order $record): bool => Gate::allows('confirm', $record))
            ->fillForm(fn (Order $record): array => [
                'quantity' => $record->quantity,
                'address' => $record->address,
            ])
            ->schema([
                Grid::make(3)->schema([
                    OrderForm::quantityInput()->required(),
                    OrderForm::unitPriceEntry(),
                    OrderForm::totalEntry(),
                ]),
                OrderForm::addressInput()->required(),
            ])
            ->action(fn (Order $record, array $data, ConfirmOrder $confirmOrder) => self::run(
                $record,
                fn () => $confirmOrder->handle($record, self::actor(), $data),
                'Ordine confermato.',
            ));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Annulla ordine')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading("Annullare l'ordine?")
            ->modalDescription("L'ordine annullato non potrà più essere modificato.")
            ->visible(fn (Order $record): bool => Gate::allows('cancel', $record))
            ->action(fn (Order $record, CancelOrder $cancelOrder) => self::run(
                $record,
                fn () => $cancelOrder->handle($record, self::actor()),
                'Ordine annullato.',
            ));
    }

    public static function assign(): Action
    {
        return Action::make('assign')
            ->label('Assegna')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('gray')
            ->modalHeading('Assegna ordine')
            ->visible(fn (Order $record): bool => Gate::allows('assign', $record))
            ->fillForm(fn (Order $record): array => ['assigned_to' => $record->assigned_to])
            ->schema([
                Select::make('assigned_to')
                    ->label('Operatrice')
                    ->options(fn () => User::query()->assignable()->orderBy('name')->get()->pluck('full_name', 'id'))
                    ->required(),
            ])
            ->action(fn (Order $record, array $data, AssignOrder $assignOrder) => self::run(
                $record,
                fn () => $assignOrder->handle($record, User::query()->findOrFail($data['assigned_to']), self::actor()),
                'Ordine assegnato.',
            ));
    }

    /**
     * Turn business refusals (a colleague got there first, missing data) into
     * notifications, then reload the record so the UI shows the current state.
     */
    private static function run(Order $record, Closure $operation, string $successMessage): void
    {
        try {
            $operation();

            Notification::make()->title($successMessage)->success()->send();
        } catch (AuthorizationException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Ordine incompleto')
                ->body(implode(' ', $exception->validator->errors()->all()))
                ->danger()
                ->send();
        }

        $record->refresh();
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
