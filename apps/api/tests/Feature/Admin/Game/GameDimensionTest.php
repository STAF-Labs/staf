<?php

namespace Tests\Feature\Admin\Game;

use App\Models\Game\ContentType\ContentType;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Filter\Dimension;
use App\Models\Game\Game;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameDimensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_dimensions_and_values(): void
    {
        [$user, $game, $gameContentType] = $this->context();
        $baseUrl = "/api/games/{$game->id}/content-types/{$gameContentType->id}/dimensions";

        $dimensionResponse = $this
            ->actingAs($user)
            ->postJson($baseUrl, [
                'name' => 'Платформа',
                'selection_mode' => 'multiple',
                'is_filterable' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Платформа')
            ->assertJsonPath('selection_mode', 'multiple')
            ->assertJsonPath('values', []);

        $dimensionId = $dimensionResponse->json('id');
        $valueUrl = "{$baseUrl}/{$dimensionId}/values";

        $this
            ->actingAs($user)
            ->patchJson("{$baseUrl}/{$dimensionId}", [
                'name' => 'Операционная система',
                'selection_mode' => 'single',
                'is_filterable' => true,
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Операционная система')
            ->assertJsonPath('selection_mode', 'single');

        $valueResponse = $this
            ->actingAs($user)
            ->postJson($valueUrl, ['name' => 'Windows'])
            ->assertCreated()
            ->assertJsonPath('name', 'Windows')
            ->assertJsonPath('is_active', true);

        $valueId = $valueResponse->json('id');

        $this
            ->actingAs($user)
            ->patchJson("{$valueUrl}/{$valueId}", [
                'name' => 'Linux',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Linux')
            ->assertJsonPath('is_active', false);

        $this
            ->actingAs($user)
            ->getJson($baseUrl)
            ->assertOk()
            ->assertJsonPath('data.0.values.0.name', 'Linux');

        $this
            ->actingAs($user)
            ->deleteJson("{$valueUrl}/{$valueId}")
            ->assertOk();

        $this
            ->actingAs($user)
            ->deleteJson("{$baseUrl}/{$dimensionId}")
            ->assertOk();

        $this->assertDatabaseCount('dimensions', 0);
        $this->assertDatabaseCount('dimension_values', 0);
    }

    public function test_dimension_routes_reject_another_game_context(): void
    {
        [$user, $game, $gameContentType] = $this->context();
        $anotherGame = Game::query()->create([
            'name' => 'Another Game',
            'status' => 'active',
        ]);

        $this
            ->actingAs($user)
            ->getJson(
                "/api/games/{$anotherGame->id}/content-types/{$gameContentType->id}/dimensions"
            )
            ->assertNotFound();
    }

    public function test_copying_dimensions_is_idempotent_and_only_adds_missing_values(): void
    {
        [$user, $game, $targetGameContentType] = $this->context();
        $sourceContentType = ContentType::query()->create([
            'name' => 'Карты',
            'is_public' => true,
        ]);
        $sourceGameContentType = $game->gameContentTypes()->create([
            'content_type_id' => $sourceContentType->id,
        ]);
        $platform = $sourceGameContentType->dimensions()->create([
            'name' => 'Платформа',
            'selection_mode' => 'multiple',
            'is_filterable' => true,
        ]);
        $platform->values()->createMany([
            ['name' => 'Windows', 'sort_order' => 0],
            ['name' => 'Linux', 'sort_order' => 1],
        ]);
        $version = $sourceGameContentType->dimensions()->create([
            'name' => 'Версия',
            'selection_mode' => 'single',
            'is_filterable' => true,
        ]);
        $version->values()->create(['name' => '1.0', 'sort_order' => 0]);

        $existingPlatform = $targetGameContentType->dimensions()->create([
            'name' => 'Платформа',
            'slug' => 'platform-legacy-suffix',
            'selection_mode' => 'single',
            'is_filterable' => true,
        ]);
        $existingPlatform->values()->create(['name' => 'Windows']);

        $url = "/api/games/{$game->id}/content-types/{$targetGameContentType->id}/dimensions/copy";
        $payload = [
            'source_game_content_type_id' => $sourceGameContentType->id,
            'dimension_ids' => [$platform->id, $version->id],
        ];

        $this
            ->actingAs($user)
            ->postJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('created_filters', 1)
            ->assertJsonPath('reused_filters', 1)
            ->assertJsonPath('created_values', 2)
            ->assertJsonPath('skipped_values', 1);

        $this
            ->actingAs($user)
            ->postJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('created_filters', 0)
            ->assertJsonPath('reused_filters', 2)
            ->assertJsonPath('created_values', 0)
            ->assertJsonPath('skipped_values', 3);

        $this->assertSame(2, $targetGameContentType->dimensions()->count());
        $this->assertSame(
            3,
            Dimension::query()
                ->where('game_content_type_id', $targetGameContentType->id)
                ->withCount('values')
                ->get()
                ->sum('values_count')
        );
    }

    /** @return array{User, Game, GameContentType} */
    private function context(): array
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $game = Game::query()->create([
            'name' => 'Filter Game',
            'status' => 'active',
        ]);
        $contentType = ContentType::query()->create([
            'name' => 'Модификации',
            'is_public' => true,
        ]);
        $gameContentType = $game->gameContentTypes()->create([
            'content_type_id' => $contentType->id,
        ]);

        return [$user, $game, $gameContentType];
    }
}
