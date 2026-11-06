<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('orders', fn (User $user): bool => $user->is_active && $user->can('viewAny', Order::class));

// Personal channel Filament opens for its own notifications.
Broadcast::channel('App.Models.User.{id}', fn (User $user, string $id): bool => (string) $user->getKey() === $id);
