<?php

namespace App\Services;

use App\Models\Order;
use Closure;
use Illuminate\Support\Facades\DB;

class OrderLocker
{
    /**
     * Run the callback in a transaction, on a fresh copy of the order holding a
     * row lock: concurrent operations on the same order are serialized by MySQL,
     * and each one sees the state left by the previous.
     *
     * @param  Closure(Order): void  $callback
     */
    public function run(Order $order, Closure $callback): Order
    {
        return DB::transaction(function () use ($order, $callback): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            $callback($locked);

            return $locked;
        });
    }
}
