<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Panel menu sections, shown in the order of these cases.
 */
enum NavigationGroup implements HasLabel
{
    case Sales;
    case Catalog;
    case Administration;

    public function getLabel(): string
    {
        return match ($this) {
            self::Sales => 'Vendite',
            self::Catalog => 'Catalogo',
            self::Administration => 'Amministrazione',
        };
    }
}
