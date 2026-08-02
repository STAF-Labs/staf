<?php

namespace Tests\Feature\Admin\Game;

use App\Models\Game\Game;
use App\Models\Game\ContentType\ContentType;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Project\Project;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GameStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_games_with_logo_url(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $game = Game::query()->create([
            'name' => 'Listed Game',
            'status' => 'active',
        ]);

        $game->addMedia(UploadedFile::fake()->image('logo.png', 512, 512))
            ->toMediaCollection('logo');

        $this
            ->actingAs($user)
            ->getJson('/api/games')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Listed Game')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('filtered_total', 1)
            ->assertJson(fn ($json) => $json
                ->whereType('data.0.logo_url', 'string')
                ->etc());
    }

    public function test_admin_can_view_game_details(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $game = Game::query()->create([
            'name' => 'Editable Game',
            'description' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => 'Описание для редактирования.'],
                        ],
                    ],
                ],
            ],
            'released_at' => '2026-07-17',
            'status' => 'active',
        ]);

        $game->addMedia(UploadedFile::fake()->image('logo.png', 512, 512))
            ->toMediaCollection('logo');

        $this
            ->actingAs($user)
            ->getJson("/api/games/{$game->id}")
            ->assertOk()
            ->assertJsonPath('id', $game->id)
            ->assertJsonPath('name', 'Editable Game')
            ->assertJsonPath('slug', 'editable-game')
            ->assertJsonPath('released_at', '2026-07-17')
            ->assertJsonPath('status', 'active')
            ->assertJson(fn ($json) => $json
                ->whereType('logo_url', 'string')
                ->etc());
    }

    public function test_admin_can_create_game_with_banner_and_logo(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/games', [
                'name' => 'Test Game',
                'description' => json_encode([
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Описание игры.'],
                            ],
                        ],
                    ],
                ]),
                'released_at' => '2026-07-17',
                'status' => 'active',
                'banner' => UploadedFile::fake()->image('banner.webp', 1600, 600),
                'logo' => UploadedFile::fake()->image('logo.png', 512, 512),
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('name', 'Test Game')
            ->assertJsonPath('slug', 'test-game')
            ->assertJsonPath('released_at', '2026-07-17')
            ->assertJsonPath('status', 'active');

        $game = Game::query()->firstOrFail();

        $this->assertTrue($game->hasMedia('banner'));
        $this->assertTrue($game->hasMedia('logo'));
        $this->assertSame('public', $game->getFirstMedia('banner')?->disk);
        $this->assertSame('public', $game->getFirstMedia('logo')?->disk);
    }

    public function test_admin_can_update_game(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $game = Game::query()->create([
            'name' => 'Old Game',
            'released_at' => '2026-01-01',
            'status' => 'active',
        ]);

        $this
            ->actingAs($user)
            ->patchJson("/api/games/{$game->id}", [
                'name' => 'Updated Game',
                'description' => json_encode([
                    'type' => 'doc',
                    'content' => [],
                ]),
                'released_at' => '2026-07-17',
                'status' => 'suspended',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Updated Game')
            ->assertJsonPath('released_at', '2026-07-17')
            ->assertJsonPath('status', 'suspended');

        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'name' => 'Updated Game',
            'status' => 'suspended',
        ]);
    }

    public function test_admin_can_update_game_with_multipart_method_spoofing(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $game = Game::query()->create([
            'name' => 'Old Game',
            'status' => 'active',
        ]);

        $this
            ->actingAs($user)
            ->postJson("/api/games/{$game->id}", [
                '_method' => 'PATCH',
                'name' => 'Multipart Updated Game',
                'status' => 'active',
                'banner' => UploadedFile::fake()->image('banner.png', 1200, 450),
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Multipart Updated Game')
            ->assertJson(fn ($json) => $json
                ->whereType('banner_url', 'string')
                ->etc());
    }

    public function test_admin_can_delete_game(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $game = Game::query()->create([
            'name' => 'Deleted Game',
            'status' => 'active',
        ]);

        $this
            ->actingAs($user)
            ->deleteJson("/api/games/{$game->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Игра удалена.');

        $this->assertSoftDeleted('games', [
            'id' => $game->id,
        ]);
    }

    public function test_admin_can_attach_content_type_to_game(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $game = Game::query()->create([
            'name' => 'Game',
            'status' => 'active',
        ]);
        $contentType = ContentType::query()->create([
            'name' => 'Мод',
            'is_public' => true,
        ]);

        $this
            ->actingAs($user)
            ->postJson("/api/games/{$game->id}/content-types", [
                'content_type_id' => $contentType->id,
            ])
            ->assertCreated()
            ->assertJsonPath('game_id', $game->id)
            ->assertJsonPath('content_type_id', $contentType->id)
            ->assertJsonPath('content_type_name', 'Мод');

        $this->assertDatabaseHas('game_content_types', [
            'game_id' => $game->id,
            'content_type_id' => $contentType->id,
        ]);
    }

    public function test_admin_can_list_game_content_types(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $game = Game::query()->create([
            'name' => 'Game',
            'status' => 'active',
        ]);
        $contentType = ContentType::query()->create([
            'name' => 'Карта',
            'is_public' => true,
        ]);
        GameContentType::query()->create([
            'game_id' => $game->id,
            'content_type_id' => $contentType->id,
        ]);

        $this
            ->actingAs($user)
            ->getJson("/api/games/{$game->id}/content-types")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.content_type_name', 'Карта');
    }

    public function test_admin_cannot_attach_same_content_type_to_game_twice(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $game = Game::query()->create([
            'name' => 'Game',
            'status' => 'active',
        ]);
        $contentType = ContentType::query()->create([
            'name' => 'Мод',
            'is_public' => true,
        ]);
        GameContentType::query()->create([
            'game_id' => $game->id,
            'content_type_id' => $contentType->id,
        ]);

        $this
            ->actingAs($user)
            ->postJson("/api/games/{$game->id}/content-types", [
                'content_type_id' => $contentType->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content_type_id']);
    }

    public function test_admin_can_detach_unused_content_type_from_game(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $game = Game::query()->create([
            'name' => 'Game',
            'status' => 'active',
        ]);
        $contentType = ContentType::query()->create([
            'name' => 'Мод',
            'is_public' => true,
        ]);
        $gameContentType = GameContentType::query()->create([
            'game_id' => $game->id,
            'content_type_id' => $contentType->id,
        ]);

        $this
            ->actingAs($user)
            ->deleteJson("/api/games/{$game->id}/content-types/{$gameContentType->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Тип контента отключен от игры.');

        $this->assertDatabaseMissing('game_content_types', [
            'id' => $gameContentType->id,
        ]);
    }

    public function test_admin_cannot_detach_content_type_used_by_project(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $game = Game::query()->create([
            'name' => 'Game',
            'status' => 'active',
        ]);
        $contentType = ContentType::query()->create([
            'name' => 'Мод',
            'is_public' => true,
        ]);
        $gameContentType = GameContentType::query()->create([
            'game_id' => $game->id,
            'content_type_id' => $contentType->id,
        ]);
        Project::query()->create([
            'ownerable_type' => User::class,
            'ownerable_id' => $user->id,
            'game_content_type_id' => $gameContentType->id,
            'title' => 'Проект',
            'description' => [],
        ]);

        $this
            ->actingAs($user)
            ->deleteJson("/api/games/{$game->id}/content-types/{$gameContentType->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'Нельзя удалить тип контента, который используется в проектах.');

        $this->assertDatabaseHas('game_content_types', [
            'id' => $gameContentType->id,
        ]);
    }

    public function test_banner_is_required_when_creating_game(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/games', [
                'name' => 'Test Game',
                'status' => 'active',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['banner']);
    }
}
