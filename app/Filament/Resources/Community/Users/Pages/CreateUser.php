<?php

namespace App\Filament\Resources\Community\Users\Pages;

use App\Filament\Resources\Community\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
