<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Orders\UpdateOrderDetails;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UpdateOrderDetails::class)->handle($record, $data, auth()->user());
        } catch (AuthorizationException $exception) {
            // The order changed hands or state while this page was open.
            Notification::make()->title($exception->getMessage())->danger()->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
