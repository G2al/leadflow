<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderLocker;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignOrder
{
    public function __construct(private readonly OrderLocker $locker) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Order $order, User $assignee, User $actor): Order
    {
        return $this->locker->run($order, function (Order $locked) use ($assignee, $actor): void {
            Gate::forUser($actor)->authorize('assign', $locked);

            if (! User::query()->assignable()->whereKey($assignee->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'assigned_to' => "L'utente selezionato non è attivo o non può gestire ordini.",
                ]);
            }

            $locked->assigned_to = $assignee->getKey();
            $locked->status = OrderStatus::InProgress;
            $locked->save();
        });
    }
}
