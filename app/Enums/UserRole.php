<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case CallCenter = 'call_center';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Amministratore',
            self::CallCenter => 'Call center',
        };
    }
}
