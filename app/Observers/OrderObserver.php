<?php

namespace App\Observers;

use App\Events\OrderChanged;
use App\Models\Order;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Throwable;

/**
 * Announces every order change once it is committed, whichever Action made it.
 */
class OrderObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Order $order): void
    {
        $this->announce($order);
    }

    public function deleted(Order $order): void
    {
        $this->announce($order);
    }

    /** Live updates are a convenience: a websocket outage must never fail an order. */
    private function announce(Order $order): void
    {
        try {
            event(new OrderChanged($order->getKey()));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
