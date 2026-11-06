<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** Receiving the event is enough: Livewire then re-renders the table. */
    #[On('orders-changed')]
    public function refreshOrders(): void {}
}
