<?php

namespace App\Http\Requests;

use App\Services\SourceUrlNormalizer;
use App\Support\OrderRules;
use Illuminate\Foundation\Http\FormRequest;

class SubmitOrderRequest extends FormRequest
{
    /** Name of the hidden field that only bots fill in. */
    public const HONEYPOT = 'website';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // An unusable URL is never a reason to lose a request: it just becomes null.
        $this->merge([
            'source_url' => app(SourceUrlNormalizer::class)->normalize($this->input('source_url')),
        ]);
    }

    /**
     * Only these keys ever reach the order: anything else sent by the client is ignored.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...OrderRules::customer(),
            'source_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function isFromBot(): bool
    {
        return filled($this->input(self::HONEYPOT));
    }
}
