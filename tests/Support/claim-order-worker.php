<?php

/**
 * Separate OS process used by OrderConcurrencyTest.
 * Usage: php claim-order-worker.php <orderId> <userId> <startAtMicrotime>
 */

use App\Actions\Orders\ClaimOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $orderId, $userId, $startAt] = $argv;

$order = Order::query()->findOrFail($orderId);
$user = User::query()->findOrFail($userId);
$user->hasAnyRole([]); // Warm up the role cache before the starting line.

// All workers are fully booted here: release them at the same instant.
time_sleep_until((float) $startAt);

try {
    app(ClaimOrder::class)->handle($order, $user);
    echo 'claimed';
} catch (AuthorizationException) {
    echo 'rejected';
}
