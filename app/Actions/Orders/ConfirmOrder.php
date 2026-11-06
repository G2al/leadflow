<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderLocker;
use App\Support\OrderRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConfirmOrder
{
    public function __construct(private readonly OrderLocker $locker) {}

    /**
     * Quantity and address can be provided here, so the operator completes and
     * confirms the order in a single atomic step.
     *
     * @param  array{quantity?: int|string|null, address?: string|null}  $details
     *
     * @throws AuthorizationException
     * @throws ValidationException when quantity or address are missing or invalid
     */
    public function handle(Order $order, User $actor, array $details = []): Order
    {
        $validated = Validator::make($details, [
            'quantity' => OrderRules::quantity(),
            'address' => OrderRules::customer()['address'],
        ])->validate();

        return $this->locker->run($order, function (Order $locked) use ($validated, $actor): void {
            Gate::forUser($actor)->authorize('confirm', $locked);

            $locked->forceFill($validated);
            $this->ensureComplete($locked);

            $locked->total_price = $locked->totalFor($locked->quantity);
            $locked->status = OrderStatus::Confirmed;
            $locked->save();
        });
    }

    private function ensureComplete(Order $order): void
    {
        $errors = [];

        if ($order->quantity === null || $order->quantity < 1) {
            $errors['quantity'] = 'Per confermare inserisci una quantità di almeno 1.';
        }

        if (blank($order->address)) {
            $errors['address'] = "Per confermare inserisci l'indirizzo.";
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
