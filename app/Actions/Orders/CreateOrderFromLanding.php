<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\OfferUnavailableException;
use App\Models\Offer;
use App\Models\Order;
use App\Services\PhoneNormalizer;

class CreateOrderFromLanding
{
    public function __construct(private readonly PhoneNormalizer $phoneNormalizer) {}

    /**
     * @param  array{first_name: string, last_name: string, phone: string, address?: string|null, source_url?: string|null}  $data
     *
     * @throws OfferUnavailableException
     */
    public function handle(Offer $offer, array $data): Order
    {
        $offer->loadMissing('product');

        if (! $offer->acceptsRequests()) {
            throw new OfferUnavailableException;
        }

        $order = new Order([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $this->phoneNormalizer->normalize($data['phone']),
            'address' => $data['address'] ?? null,
        ]);

        $order->offer()->associate($offer);
        $order->source_url = $data['source_url'] ?? null;
        $order->unit_price = $offer->effectivePrice();
        $order->status = OrderStatus::New;
        $order->save();

        return $order;
    }
}
