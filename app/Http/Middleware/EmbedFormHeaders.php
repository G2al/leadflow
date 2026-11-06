<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the public form render inside third-party landing pages while confining
 * what the page itself can load or submit to.
 */
class EmbedFormHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(32);
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'none'",
            "script-src 'nonce-{$nonce}'",
            "style-src 'nonce-{$nonce}'",
            "connect-src 'self'",
            "form-action 'self'",
            "base-uri 'none'",
            'frame-ancestors *',
        ]));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
