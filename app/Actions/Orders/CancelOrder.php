<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderLocker;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class CancelOrder
{
    public function __construct(private readonly OrderLocker $locker) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(Order $order, User $actor): Order
    {
        return $this->locker->run($order, function (Order $locked) use ($actor): void {
            Gate::forUser($actor)->authorize('cancel', $locked);

            $locked->status = OrderStatus::Cancelled;
            $locked->save();
        });
    }
}
