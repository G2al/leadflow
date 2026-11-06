<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case New = 'nuovo';
    case InProgress = 'in_lavorazione';
    case Confirmed = 'confermato';
    case Cancelled = 'annullato';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nuovo',
            self::InProgress => 'In lavorazione',
            self::Confirmed => 'Confermato',
            self::Cancelled => 'Annullato',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::InProgress => 'warning',
            self::Confirmed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
