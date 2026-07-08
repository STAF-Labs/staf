<?php

namespace App\Filament\Resources\Community\Organizations\Pages;

use App\Filament\Resources\Community\Organizations\OrganizationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;
}
