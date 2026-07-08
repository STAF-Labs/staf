<?php

namespace App\Filament\Resources\Community\Organizations\Schemas;

use App\Enums\CommonStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('summary'),
                TextInput::make('description'),
                TextInput::make('website_urls'),
                TextInput::make('contact_email')
                    ->email(),
                Toggle::make('visibility')
                    ->required(),
                Select::make('status')
                    ->options(CommonStatus::class)
                    ->default('active')
                    ->required(),
                DateTimePicker::make('verified_at'),
            ]);
    }
}
