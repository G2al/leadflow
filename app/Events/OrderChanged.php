<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Tells connected panels that an order changed. It carries only the id: browsers
 * reload the data through the usual authorized requests, so nothing about the
 * customer travels on the websocket.
 */
class OrderChanged implements ShouldBroadcastNow
{
    public function __construct(public readonly int $orderId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('orders')];
    }

    public function broadcastAs(): string
    {
        return 'order.changed';
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->orderId];
    }
}
