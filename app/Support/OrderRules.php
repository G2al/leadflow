<?php

namespace App\Support;

use App\Models\Order;

/**
 * Validation shared by the public form and the Filament order form.
 */
final class OrderRules
{
    /** Permissive on formatting, strict on charset: from 6 to 15 digits overall. */
    public const PHONE_REGEX = '/^(?=(?:\D*\d){6,15}\D*$)\+?[0-9\s().\/-]+$/';

    /**
     * @return array<string, list<string>>
     */
    public static function customer(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:'.self::PHONE_REGEX],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function quantity(): array
    {
        return ['nullable', 'integer', 'min:1', 'max:'.Order::MAX_QUANTITY];
    }
}
