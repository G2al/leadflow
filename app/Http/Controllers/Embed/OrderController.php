<?php

namespace App\Http\Controllers\Embed;

use App\Actions\Orders\CreateOrderFromLanding;
use App\Exceptions\OfferUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitOrderRequest;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    private const SUCCESS_MESSAGE = 'Richiesta inviata con successo. Sarai ricontattato a breve.';

    public function store(SubmitOrderRequest $request, Offer $offer, CreateOrderFromLanding $createOrder): JsonResponse
    {
        // Bots get the same answer as everyone else, so the trap stays invisible.
        if ($request->isFromBot()) {
            return response()->json(['message' => self::SUCCESS_MESSAGE], 201);
        }

        try {
            $createOrder->handle($offer, $request->validated());
        } catch (OfferUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 410);
        }

        return response()->json(['message' => self::SUCCESS_MESSAGE], 201);
    }
}
