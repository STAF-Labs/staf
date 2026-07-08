<?php

namespace App\Filament\Resources\Community\UserProfiles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'id')
                    ->required(),
                TextInput::make('display_name'),
                TextInput::make('bio'),
                TextInput::make('website_urls'),
                DatePicker::make('birthday'),
                Toggle::make('is_public')
                    ->required(),
                Toggle::make('show_online_status')
                    ->required(),
                Toggle::make('show_last_seen_at')
                    ->required(),
            ]);
    }
}
