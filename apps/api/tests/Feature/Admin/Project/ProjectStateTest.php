<?php

namespace Tests\Feature\Admin\Project;

use App\Enums\CommonStatus;
use App\Enums\Project\ProjectPublicationStatus;
use App\Enums\Project\ProjectStatus;
use App\Models\Game\ContentType\ContentType;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Filter\Dimension;
use App\Models\Game\Game;
use App\Models\Game\Project\Project;
use App\Models\Game\Project\ProjectRelease;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_uses_safe_state_defaults(): void
    {
        [$user, $gameContentType] = $this->projectContext();

        $project = Project::query()->create([
            ...$this->projectPayload($user, $gameContentType),
        ]);

        $this->assertSame(0, $project->percentage_complete);
        $this->assertSame(ProjectPublicationStatus::PRIVATE, $project->publication_status);
        $this->assertSame(ProjectStatus::DRAFT, $project->status);
    }

    public function test_project_is_saved_as_a_draft_and_published_in_a_later_request(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();

        $draftResponse = $this
            ->actingAs($user)
            ->postJson('/api/projects', [
                ...$this->projectPayload($user, $gameContentType),
                'percentage_complete' => 83,
                'publication_status' => 'public',
                'status' => 'published',
                'summary' => $this->filledDocument('Краткое описание проекта.'),
                'logo' => UploadedFile::fake()->image('logo.png', 512, 512),
            ]);

        $draftResponse
            ->assertCreated()
            ->assertJsonPath('description', null)
            ->assertJsonPath('percentage_complete', 83)
            ->assertJsonPath('publication_status', 'private')
            ->assertJsonPath('status', 'draft');

        $project = Project::query()->firstOrFail();

        $publishResponse = $this
            ->actingAs($user)
            ->patchJson("/api/projects/{$project->id}", [
                'description' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Описание проекта.'],
                            ],
                        ],
                    ],
                ],
                'percentage_complete' => 100,
                'publication_status' => 'public',
                'status' => 'published',
            ]);

        $publishResponse
            ->assertOk()
            ->assertJsonPath('title', 'State Project')
            ->assertJsonPath('percentage_complete', 100)
            ->assertJsonPath('publication_status', 'public')
            ->assertJsonPath('status', 'published');

        $project->refresh();

        $this->assertSame(100, $project->percentage_complete);
        $this->assertSame(ProjectPublicationStatus::PUBLIC, $project->publication_status);
        $this->assertSame(ProjectStatus::PUBLISHED, $project->status);
    }

    public function test_project_rich_text_fields_must_contain_text_when_present(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();

        $this
            ->actingAs($user)
            ->postJson('/api/projects', [
                ...$this->projectPayload($user, $gameContentType),
                'summary' => [
                    'type' => 'doc',
                    'content' => [],
                ],
                'logo' => UploadedFile::fake()->image('logo.png', 512, 512),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['summary']);

        $project = Project::query()->create($this->projectPayload($user, $gameContentType));

        $this
            ->actingAs($user)
            ->patchJson("/api/projects/{$project->id}", [
                'description' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph'],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    }

    public function test_project_state_fields_are_validated(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();

        $project = Project::query()->create($this->projectPayload($user, $gameContentType));

        $this
            ->actingAs($user)
            ->patchJson("/api/projects/{$project->id}", [
                'percentage_complete' => 101,
                'publication_status' => 'hidden',
                'status' => 'archived',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'percentage_complete',
                'publication_status',
                'status',
            ]);
    }

    public function test_project_must_be_ready_before_publication(): void
    {
        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectPayload($user, $gameContentType));
        $dimension = Dimension::query()->create([
            'game_content_type_id' => $gameContentType->id,
            'name' => 'Категория',
            'selection_mode' => 'single',
            'applies_to' => 'project',
            'is_filterable' => true,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $dimension->values()->create([
            'name' => 'Приключения',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->patchJson("/api/projects/{$project->id}", [
                'status' => 'published',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'logo',
                'summary',
                'description',
                'dimension_value_ids',
            ]);
    }

    public function test_project_can_be_published_without_optional_catalogue_fields_or_releases(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();
        $dimension = Dimension::query()->create([
            'game_content_type_id' => $gameContentType->id,
            'name' => 'Категория',
            'selection_mode' => 'single',
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
        $project = Project::query()->create([
            ...$this->projectPayload($user, $gameContentType),
            'summary' => $this->filledDocument('Краткое описание проекта.'),
            'description' => $this->filledDocument('Подробное описание проекта.'),
        ]);
        $project->addMedia(UploadedFile::fake()->image('logo.png', 512, 512))
            ->toMediaCollection('logo');
        $project->dimensionValues()->sync([$value->id]);

        $this
            ->actingAs($user)
            ->patchJson("/api/projects/{$project->id}", [
                'status' => 'published',
                'publication_status' => 'public',
                'website_urls' => [],
                'tags' => [],
                'licence_name' => null,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'published')
            ->assertJsonPath('publication_status', 'public');

        $this->assertSame(ProjectStatus::PUBLISHED, $project->refresh()->status);
        $this->assertSame(0, $project->releases()->count());
    }

    public function test_project_with_releases_cannot_be_moved_to_unpublished_status(): void
    {
        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create([
            ...$this->projectPayload($user, $gameContentType),
            'status' => 'published',
        ]);
        ProjectRelease::query()->create([
            'project_id' => $project->id,
            'title' => '1.0.0',
            'slug' => '1-0-0',
            'type' => 'release',
            'status' => 'on_moderation',
            'changelog' => $this->filledDocument('Первый релиз.'),
            'released_at' => today(),
        ]);

        $this
            ->actingAs($user)
            ->patchJson("/api/projects/{$project->id}", [
                'status' => 'draft',
            ])
            ->assertConflict();

        $this->assertSame(ProjectStatus::PUBLISHED, $project->refresh()->status);
    }

    public function test_project_index_includes_owner_name_and_updated_date(): void
    {
        [$user, $gameContentType] = $this->projectContext();
        $user->userProfile()->create([
            'display_name' => 'Project Author',
        ]);
        $project = Project::query()->create($this->projectPayload($user, $gameContentType));

        $this
            ->actingAs($user)
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonPath('data.0.id', $project->id)
            ->assertJsonPath('data.0.owner_name', 'Project Author')
            ->assertJsonPath('data.0.updated_at', $project->updated_at?->toISOString());
    }

    public function test_user_cannot_update_another_users_draft(): void
    {
        [$owner, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectPayload($owner, $gameContentType));
        $otherUser = User::query()->create([
            'username' => 'other-user',
            'email' => 'other@example.com',
            'password' => 'password',
        ]);

        $this
            ->actingAs($otherUser)
            ->patchJson("/api/projects/{$project->id}", [
                'percentage_complete' => 100,
            ])
            ->assertForbidden();

        $this->assertSame(0, $project->refresh()->percentage_complete);
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
            'name' => 'State Game',
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
            'title' => 'State Project',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filledDocument(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $text],
                    ],
                ],
            ],
        ];
    }
}
