<?php

namespace App\Services;

use App\Models\Offer;

class OfferEmbedCode
{
    public function scriptUrl(Offer $offer): string
    {
        return rtrim((string) config('app.url'), '/').route('embed.script', $offer, absolute: false);
    }

    /** The snippet the admin hands to whoever manages the landing page. */
    public function snippet(Offer $offer): string
    {
        return '<script async src="'.e($this->scriptUrl($offer)).'"></script>';
    }
}
