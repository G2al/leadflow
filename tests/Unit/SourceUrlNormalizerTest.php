<?php

namespace Tests\Unit;

use App\Services\SourceUrlNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SourceUrlNormalizerTest extends TestCase
{
    #[DataProvider('urls')]
    public function test_it_normalizes(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, (new SourceUrlNormalizer)->normalize($input));
    }

    /**
     * @return array<string, array{mixed, string|null}>
     */
    public static function urls(): array
    {
        return [
            'origin and path' => ['https://example.com/landing/a', 'https://example.com/landing/a'],
            'drops query and hash' => ['https://example.com/a?email=x@y.it&t=1#form', 'https://example.com/a'],
            'keeps port' => ['http://127.0.0.1:9000/test.html', 'http://127.0.0.1:9000/test.html'],
            'lowercases host' => ['HTTPS://EXAMPLE.com/Path', 'https://example.com/Path'],
            'adds root path' => ['https://example.com', 'https://example.com/'],
            'drops credentials' => ['https://user:secret@example.com/a', 'https://example.com/a'],
            'javascript' => ['javascript:alert(1)', null],
            'data' => ['data:text/html,<script>alert(1)</script>', null],
            'file' => ['file:///etc/passwd', null],
            'markup' => ['https://example.com/<script>', null],
            'quote' => ['https://example.com/"onclick', null],
            'whitespace' => ['https://example.com/a b', null],
            'no host' => ['https://', null],
            'relative' => ['/landing', null],
            'null' => [null, null],
            'not a string' => [['https://example.com'], null],
            'too long' => ['https://example.com/'.str_repeat('a', 5000), null],
        ];
    }
}
