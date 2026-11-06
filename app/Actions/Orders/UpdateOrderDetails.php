<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderLocker;
use App\Services\PhoneNormalizer;
use App\Support\OrderRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateOrderDetails
{
    public function __construct(
        private readonly OrderLocker $locker,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    /**
     * Unit price is intentionally not accepted: only the fields validated here
     * can change, and the total is always derived server-side.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Order $order, array $data, User $actor): Order
    {
        $validated = Validator::make($data, [
            ...OrderRules::customer(),
            'quantity' => OrderRules::quantity(),
        ])->validate();

        return $this->locker->run($order, function (Order $locked) use ($validated, $actor): void {
            Gate::forUser($actor)->authorize('update', $locked);

            $locked->fill([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $this->phoneNormalizer->normalize($validated['phone']),
                'address' => $validated['address'] ?? null,
            ]);
            $locked->quantity = $validated['quantity'] ?? null;
            $locked->total_price = $locked->totalFor($locked->quantity);
            $locked->save();
        });
    }
}
