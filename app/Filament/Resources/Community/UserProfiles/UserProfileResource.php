<?php

namespace App\Filament\Resources\Community\UserProfiles;

use App\Filament\Resources\Community\UserProfiles\Pages\CreateUserProfile;
use App\Filament\Resources\Community\UserProfiles\Pages\EditUserProfile;
use App\Filament\Resources\Community\UserProfiles\Pages\ListUserProfiles;
use App\Filament\Resources\Community\UserProfiles\Schemas\UserProfileForm;
use App\Filament\Resources\Community\UserProfiles\Tables\UserProfilesTable;
use App\Models\User\UserProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserProfileResource extends Resource
{
    protected static ?string $model = UserProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    public static function form(Schema $schema): Schema
    {
        return UserProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserProfiles::route('/'),
            'create' => CreateUserProfile::route('/create'),
            'edit' => EditUserProfile::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
