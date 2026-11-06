<?php

namespace App\Services;

class SourceUrlNormalizer
{
    private const MAX_LENGTH = 2048;

    /**
     * Reduce a browser-declared URL to origin + path, dropping query string and
     * fragment (they may carry personal data). The result is informative metadata,
     * not proof of origin; anything that is not a clean http(s) URL becomes null.
     */
    public function normalize(mixed $url): ?string
    {
        if (! is_string($url) || $url === '' || strlen($url) > self::MAX_LENGTH * 2) {
            return null;
        }

        if (preg_match('/[\x00-\x20\x7f<>"\'`\\\\]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        if (blank($parts['host'] ?? null)) {
            return null;
        }

        $normalized = strtolower($parts['scheme']).'://'.strtolower($parts['host'])
            .(isset($parts['port']) ? ":{$parts['port']}" : '')
            .($parts['path'] ?? '/');

        if (strlen($normalized) > self::MAX_LENGTH || filter_var($normalized, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $normalized;
    }
}
