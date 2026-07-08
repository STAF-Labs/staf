<?php

namespace App\Filament\Resources\Community\Users\Schemas;

use App\Enums\CommonStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->required(),
                Select::make('status')
                    ->options(CommonStatus::class)
                    ->default('active')
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                DateTimePicker::make('last_seen_at'),
            ]);
    }
}
