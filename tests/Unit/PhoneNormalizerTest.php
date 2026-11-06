<?php

namespace Tests\Unit;

use App\Services\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_it_keeps_digits_and_the_leading_plus(): void
    {
        $normalizer = new PhoneNormalizer;

        $this->assertSame('+393331234567', $normalizer->normalize(' +39 (333) 123-4567 '));
        $this->assertSame('021234567', $normalizer->normalize('02/1234567'));
    }
}
