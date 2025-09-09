<?php

namespace App\Filament\Resources\TravelFusionPasswordResource\Pages;

use App\Filament\Resources\TravelFusionPasswordResource;
use App\Services\TravelFusion\TravelFusionService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class EditTravelFusionPassword extends EditRecord
{
    protected static string $resource = TravelFusionPasswordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Only change password in TravelFusion if this record is active
        if ($this->record->is_active) {
            try {
//                $service = new TravelFusionService();
//                $service->changePassword($this->record->password);
                // Deactivate all other passwords
                $this->record->where('id', '!=', $this->record->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                Log::info('TravelFusion password changed successfully', [
                    'username' => $this->record->username,
                    'changed_at' => $this->record->changed_at,
                ]);

                Notification::make()
                    ->title('Password changed successfully')
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                Log::error('Failed to change TravelFusion password', [
                    'error' => $e->getMessage(),
                    'username' => $this->record->username,
                ]);

                // Deactivate this record since the password change failed
                $this->record->update(['is_active' => false]);

                // Notify the user
                Notification::make()
                    ->title('Failed to change password in TravelFusion')
                    ->body('The record has been deactivated.')
                    ->danger()
                    ->send();
            }
        }
    }
}
