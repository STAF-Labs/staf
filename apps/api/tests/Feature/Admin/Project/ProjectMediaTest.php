<?php

namespace Tests\Feature\Admin\Project;

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
                'licence' => UploadedFile::fake()->createWithContent(
                    'LICENCE.pdf',
                    "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"
                ),
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('title', 'Media Project')
            ->assertJsonCount(2, 'screenshot_urls')
            ->assertJson(fn ($json) => $json
                ->whereType('logo_url', 'string')
                ->whereType('licence_url', 'string')
                ->whereType('screenshot_urls.0', 'string')
                ->whereType('screenshot_urls.1', 'string')
                ->etc());

        $project = Project::query()->firstOrFail();

        $this->assertCount(1, $project->getMedia('logo'));
        $this->assertCount(2, $project->getMedia('screenshots'));
        $this->assertCount(1, $project->getMedia('licence'));
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
    }

    public function test_single_project_media_are_replaced_and_screenshots_are_multiple(): void
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
        $project->addMedia(UploadedFile::fake()->createWithContent('old.txt', 'Old licence'))
            ->toMediaCollection('licence');
        $project->unsetRelation('media');
        $project->addMedia(UploadedFile::fake()->createWithContent('new.txt', 'New licence'))
            ->toMediaCollection('licence');

        $project->refresh();

        $this->assertCount(1, $project->getMedia('logo'));
        $this->assertCount(2, $project->getMedia('screenshots'));
        $this->assertCount(1, $project->getMedia('licence'));
        $this->assertSame('new-logo', $project->getFirstMedia('logo')?->name);
        $this->assertSame('new', $project->getFirstMedia('licence')?->name);
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
