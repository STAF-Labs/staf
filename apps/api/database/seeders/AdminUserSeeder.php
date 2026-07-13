<?php

namespace Database\Seeders;

use App\Enums\CommonStatus;
use App\Enums\MembershipStatus;
use App\Enums\Org\OrgMemberRole;
use App\Models\Org\Organization;
use App\Models\Org\OrganizationMember;
use App\Models\User\User;
use App\Models\User\UserProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
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

        $adminRole = Role::findOrCreate('admin', 'web');
        $regularRole = Role::findOrCreate('regular', 'web');

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@staf.ru'],
            [
                'username' => 'admin',
                'password' => 'password',
                'status' => CommonStatus::ACTIVE,
            ],
        );

        $admin->forceFill([
            'email_verified_at' => now(),
        ])->save();

        UserProfile::query()->updateOrCreate(
            ['user_id' => $admin->id],
            [
                'display_name' => 'Admin',
                'bio' => ['ru' => 'Администратор'],
                'website_urls' => [],
                'is_public' => true,
                'show_online_status' => true,
                'show_last_seen_at' => true,
            ],
        );

        $admin->syncRoles([$adminRole]);

        /** @var Collection<int, User> $regularUsers */
        $regularUsers = collect(range(1, 5))->map(function (int $number) use ($regularRole): User {
            $user = User::query()->updateOrCreate(
                ['email' => "user{$number}@staf.ru"],
                [
                    'username' => "user{$number}",
                    'password' => 'password',
                    'status' => fake()->randomElement(CommonStatus::cases()),
                    'last_seen_at' => fake()->optional(0.8)->dateTimeBetween('-14 days'),
                ],
            );

            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            UserProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'display_name' => fake('ru_RU')->name(),
                    'bio' => ['ru' => fake('ru_RU')->sentence(10)],
                    'website_urls' => fake()->boolean(45) ? [fake()->url()] : [],
                    'birthday' => fake()->dateTimeBetween('-42 years', '-18 years')->format('Y-m-d'),
                    'is_public' => fake()->boolean(85),
                    'show_online_status' => fake()->boolean(75),
                    'show_last_seen_at' => fake()->boolean(75),
                ],
            );

            $user->syncRoles([$regularRole]);

            return $user;
        });

        $organizations = collect([
            [
                'name' => 'STAF Publishing',
                'slug' => 'staf-publishing',
                'summary' => 'Издательская организация для демо-данных.',
                'contact_email' => 'publishing@staf.ru',
            ],
            [
                'name' => 'Northwind Games',
                'slug' => 'northwind-games',
                'summary' => 'Игровая студия для проверки членств.',
                'contact_email' => 'hello@northwind.test',
            ],
        ])->map(fn (array $attributes): Organization => Organization::query()->updateOrCreate(
            ['slug' => $attributes['slug']],
            [
                'created_by' => $admin->id,
                'name' => $attributes['name'],
                'summary' => $attributes['summary'],
                'description' => ['ru' => fake('ru_RU')->paragraph()],
                'website_urls' => [fake()->url()],
                'contact_email' => $attributes['contact_email'],
                'is_visible' => fake()->boolean(85),
                'status' => fake()->randomElement(CommonStatus::cases()),
                'verified_at' => fake()->boolean(70) ? now()->subDays(fake()->numberBetween(1, 90)) : null,
            ],
        ));

        $organizations->each(function (Organization $organization) use ($admin): void {
            OrganizationMember::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $admin->id,
                ],
                [
                    'invited_by' => null,
                    'public_title' => 'Owner',
                    'role' => OrgMemberRole::OWNER,
                    'status' => MembershipStatus::ACTIVE,
                    'invited_at' => now()->subDays(30),
                ],
            );
        });

        $regularUsers->each(function (User $user) use ($admin, $organizations): void {
            $organizations
                ->random(fake()->numberBetween(1, $organizations->count()))
                ->each(function (Organization $organization) use ($admin, $user): void {
                    OrganizationMember::query()->updateOrCreate(
                        [
                            'organization_id' => $organization->id,
                            'user_id' => $user->id,
                        ],
                        [
                            'invited_by' => $admin->id,
                            'public_title' => fake('ru_RU')->jobTitle(),
                            'role' => fake()->randomElement([
                                OrgMemberRole::MAINTAINER,
                                OrgMemberRole::MEMBER,
                                OrgMemberRole::READER,
                            ]),
                            'status' => fake()->randomElement(MembershipStatus::cases()),
                            'invited_at' => fake()->dateTimeBetween('-60 days'),
                        ],
                    );
                });
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
