<?php

namespace App\Filament\Resources\Community\UserProfiles\Pages;

use App\Filament\Resources\Community\UserProfiles\UserProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserProfile extends CreateRecord
{
    protected static string $resource = UserProfileResource::class;
}
