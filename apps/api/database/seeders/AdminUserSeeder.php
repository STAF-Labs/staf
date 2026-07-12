<?php

namespace Database\Seeders;

use App\Models\User\User;
use App\Models\User\UserProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::findOrCreate('admin', 'web');

        $user = User::query()->updateOrCreate(
            ['email' => 'admin@staf.ru'],
            [
                'username' => 'admin',
                'password' => 'password',
                'status' => 'active',
            ],
        );

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        UserProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => 'Admin',
                'bio' => ['ru' => 'Администратор'],
                'website_urls' => [],
                'is_public' => true,
                'show_online_status' => true,
                'show_last_seen_at' => true,
            ],
        );

        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
