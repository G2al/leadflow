<?php

namespace App\Http\Controllers\Embed;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScriptController extends Controller
{
    /**
     * The loader is the same whatever the offer state: the iframe decides what to
     * show, so a cached script never outlives a deactivation.
     */
    public function __invoke(Request $request, Offer $offer): Response
    {
        $script = view('embed.loader', [
            'formUrl' => route('embed.form', $offer),
            'formOrigin' => $request->getSchemeAndHttpHost(),
        ])->render();

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
