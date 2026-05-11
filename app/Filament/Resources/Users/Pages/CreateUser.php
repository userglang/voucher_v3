<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [

            Action::make('back')
                ->label('Back')
                ->url(UserResource::getUrl('index'))
                ->color('warning')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->hidden();
    }
}
