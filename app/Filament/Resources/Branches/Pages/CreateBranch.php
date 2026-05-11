<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateBranch extends CreateRecord
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [

            Action::make('back')
                ->label('Back')
                ->url(BranchResource::getUrl('index'))
                ->color('warning')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->hidden();
    }
}
