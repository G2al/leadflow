<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    #[On('orders-changed')]
    public function refreshOrder(): void
    {
        $this->getRecord()->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            OrderActions::claim(),
            EditAction::make()->label('Modifica dati'),
            OrderActions::confirm(),
            OrderActions::cancel(),
            OrderActions::assign(),
            OrderActions::delete(),
        ];
    }
}
