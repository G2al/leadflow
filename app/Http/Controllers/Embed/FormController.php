<?php

namespace App\Http\Controllers\Embed;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\SourceUrlNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FormController extends Controller
{
    public function __invoke(Request $request, Offer $offer, SourceUrlNormalizer $normalizer): Response
    {
        $offer->load('product');

        if (! $offer->acceptsRequests()) {
            return response()->view('embed.unavailable', status: 410);
        }

        return response()->view('embed.form', [
            'submitUrl' => route('embed.orders.store', $offer),
            'sourceUrl' => $normalizer->normalize($request->query('source')),
            'privacyUrl' => config('leadflow.privacy_url'),
            'isPreview' => $request->boolean('preview'),
        ]);
    }
}
