<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Services\OfferEmbedCode;
use Illuminate\Contracts\View\View;

/**
 * A minimal landing page that runs the real embed snippet, so an admin can try
 * an offer without publishing anything. Access is enforced by the panel routes.
 */
class OfferTestLandingController extends Controller
{
    public function __invoke(Offer $offer, OfferEmbedCode $embedCode): View
    {
        return view('offers.test-landing', [
            'offer' => $offer,
            'snippet' => $embedCode->snippet($offer),
        ]);
    }
}
