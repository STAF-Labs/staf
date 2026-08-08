<?php

namespace Tests\Feature\Admin\Project;

use App\Enums\CommonStatus;
use App\Models\Game\ContentType\ContentType;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Filter\Dimension;
use App\Models\Game\Game;
use App\Models\Game\Project\Project;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_project_with_media(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/projects', [
                ...$this->projectPayload($user, $gameContentType),
                'logo' => UploadedFile::fake()->image('logo.png', 512, 512),
                'screenshots' => [
                    UploadedFile::fake()->image('first.png', 1280, 720),
                    UploadedFile::fake()->image('second.png', 1920, 1080),
                ],
                'licence_name' => 'MIT',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('title', 'Media Project')
            ->assertJsonPath('licence_name', 'MIT')
            ->assertJsonCount(2, 'screenshot_urls')
            ->assertJson(fn ($json) => $json
                ->whereType('logo_url', 'string')
                ->whereType('screenshot_urls.0', 'string')
                ->whereType('screenshot_urls.1', 'string')
                ->etc());

        $project = Project::query()->firstOrFail();

        $this->assertSame('MIT', $project->licence_name);
        $this->assertCount(1, $project->getMedia('logo'));
        $this->assertCount(2, $project->getMedia('screenshots'));
    }

    public function test_logo_is_required_when_creating_project(): void
    {
        [$user, $gameContentType] = $this->projectContext();

        $this
            ->actingAs($user)
            ->postJson('/api/projects', $this->projectPayload($user, $gameContentType))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_admin_can_update_project_licence_name(): void
    {
        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create([
            ...$this->projectPayload($user, $gameContentType),
            'licence_name' => 'MIT',
        ]);

        $this
            ->actingAs($user)
            ->patchJson("/api/projects/{$project->id}", [
                'licence_name' => 'Apache-2.0',
            ])
            ->assertOk()
            ->assertJsonPath('licence_name', 'Apache-2.0');

        $this->assertSame('Apache-2.0', $project->refresh()->licence_name);
    }

    public function test_licence_name_cannot_exceed_128_characters(): void
    {
        [$user, $gameContentType] = $this->projectContext();

        $this
            ->actingAs($user)
            ->postJson('/api/projects', [
                ...$this->projectPayload($user, $gameContentType),
                'licence_name' => str_repeat('a', 129),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['licence_name']);
    }

    public function test_licence_name_must_be_from_catalogue(): void
    {
        [$user, $gameContentType] = $this->projectContext();

        $this
            ->actingAs($user)
            ->postJson('/api/projects', [
                ...$this->projectPayload($user, $gameContentType),
                'licence_name' => 'mit licence',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['licence_name']);
    }

    public function test_admin_must_select_required_project_dimension_and_it_is_saved(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();
        $dimension = Dimension::query()->create([
            'game_content_type_id' => $gameContentType->id,
            'name' => 'Категория',
            'selection_mode' => 'multiple',
            'applies_to' => 'project',
            'is_filterable' => true,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $value = $dimension->values()->create([
            'name' => 'Приключения',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $replacementValue = $dimension->values()->create([
            'name' => 'Технологии',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $payload = [
            ...$this->projectPayload($user, $gameContentType),
            'logo' => UploadedFile::fake()->image('logo.png', 512, 512),
        ];

        $this
            ->actingAs($user)
            ->postJson('/api/projects', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dimension_value_ids']);

        $this
            ->actingAs($user)
            ->postJson('/api/projects', [
                ...$payload,
                'logo' => UploadedFile::fake()->image('logo.png', 512, 512),
                'dimension_value_ids' => [$value->id],
            ])
            ->assertCreated()
            ->assertJsonPath('dimension_value_ids.0', $value->id);

        $this->assertDatabaseHas('project_dimension_values', [
            'project_id' => Project::query()->firstOrFail()->id,
            'dimension_value_id' => $value->id,
        ]);

        $project = Project::query()->firstOrFail();

        $this
            ->actingAs($user)
            ->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('dimension_value_ids.0', $value->id)
            ->assertJson(fn ($json) => $json->whereType('logo_url', 'string')->etc());

        $this
            ->actingAs($user)
            ->patchJson("/api/projects/{$project->id}", [
                'dimension_value_ids' => [$replacementValue->id],
            ])
            ->assertOk()
            ->assertJsonPath('dimension_value_ids.0', $replacementValue->id);

        $this->assertDatabaseMissing('project_dimension_values', [
            'project_id' => $project->id,
            'dimension_value_id' => $value->id,
        ]);
        $this->assertDatabaseHas('project_dimension_values', [
            'project_id' => $project->id,
            'dimension_value_id' => $replacementValue->id,
        ]);
    }

    public function test_logo_is_replaced_and_screenshots_are_multiple(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectPayload($user, $gameContentType));

        $project->addMedia(UploadedFile::fake()->image('old-logo.png', 256, 256))
            ->toMediaCollection('logo');
        $project->addMedia(UploadedFile::fake()->image('new-logo.png', 512, 512))
            ->toMediaCollection('logo');
        $project->addMedia(UploadedFile::fake()->image('first.png', 1280, 720))
            ->toMediaCollection('screenshots');
        $project->addMedia(UploadedFile::fake()->image('second.png', 1280, 720))
            ->toMediaCollection('screenshots');
        $project->refresh();

        $this->assertCount(1, $project->getMedia('logo'));
        $this->assertCount(2, $project->getMedia('screenshots'));
        $this->assertSame('new-logo', $project->getFirstMedia('logo')?->name);
    }

    public function test_logo_can_be_replaced_during_partial_draft_update(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectPayload($user, $gameContentType));
        $project->addMedia(UploadedFile::fake()->image('old-logo.png', 256, 256))
            ->toMediaCollection('logo');

        $this
            ->actingAs($user)
            ->post("/api/projects/{$project->id}", [
                '_method' => 'PATCH',
                'logo' => UploadedFile::fake()->image('new-logo.png', 512, 512),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('title', 'Media Project');

        $project->refresh();

        $this->assertCount(1, $project->getMedia('logo'));
        $this->assertSame('new-logo', $project->getFirstMedia('logo')?->name);
    }

    /**
     * @return array{User, GameContentType}
     */
    private function projectContext(): array
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'status' => CommonStatus::ACTIVE,
        ]);
        $game = Game::query()->create([
            'name' => 'Media Game',
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

        return [$user, $gameContentType];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectPayload(User $user, GameContentType $gameContentType): array
    {
        return [
            'ownerable_type' => User::class,
            'ownerable_id' => $user->id,
            'game_content_type_id' => $gameContentType->id,
            'title' => 'Media Project',
            'description' => [
                'type' => 'doc',
                'content' => [],
            ],
            'status' => 'draft',
        ];
    }
}
