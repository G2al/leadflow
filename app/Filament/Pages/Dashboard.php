<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Admin-only: the panel root redirects each user to the first page they can
 * access, which for call center operators is the orders list.
 */
class Dashboard extends BaseDashboard
{
    protected static string $routePath = 'dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
