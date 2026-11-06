<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderLocker;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class ClaimOrder
{
    public function __construct(private readonly OrderLocker $locker) {}

    /**
     * @throws AuthorizationException when the order was taken in the meantime
     */
    public function handle(Order $order, User $actor): Order
    {
        return $this->locker->run($order, function (Order $locked) use ($actor): void {
            Gate::forUser($actor)->authorize('claim', $locked);

            $locked->assigned_to = $actor->getKey();
            $locked->status = OrderStatus::InProgress;
            $locked->save();
        });
    }
}
