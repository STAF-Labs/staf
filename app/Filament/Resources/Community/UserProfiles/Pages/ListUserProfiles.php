<?php

namespace App\Filament\Resources\Community\UserProfiles\Pages;

use App\Filament\Resources\Community\UserProfiles\UserProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserProfiles extends ListRecords
{
    protected static string $resource = UserProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
