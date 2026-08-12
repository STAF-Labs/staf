<?php

namespace Tests\Feature\Admin\Project;

use App\Actions\Admin\Project\SaveProjectStep;
use App\Enums\CommonStatus;
use App\Enums\MembershipStatus;
use App\Enums\Project\ProjectMemberRole;
use App\Enums\Project\ProjectReleaseStatus;
use App\Models\Game\ContentType\ContentType;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Filter\Dimension;
use App\Models\Game\Game;
use App\Models\Game\Project\Project;
use App\Models\Game\Project\ProjectMember;
use App\Models\Game\Project\ProjectRelease;
use App\Models\User\User;
use RuntimeException;
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
            ->assertJsonCount(2, 'screenshots')
            ->assertJsonCount(2, 'screenshot_urls')
            ->assertJson(fn ($json) => $json
                ->whereType('logo_url', 'string')
                ->whereType('screenshots.0.id', 'integer')
                ->whereType('screenshots.0.url', 'string')
                ->whereType('screenshots.0.order', 'integer')
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

    public function test_admin_can_create_project_release_with_file_and_release_filters(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectPayload($user, $gameContentType));
        $dimension = Dimension::query()->create([
            'game_content_type_id' => $gameContentType->id,
            'name' => 'Версия игры',
            'selection_mode' => 'single',
            'applies_to' => 'release',
            'is_filterable' => true,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $value = $dimension->values()->create([
            'name' => '1.20',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->postJson("/api/projects/{$project->id}/releases", [
                'file' => UploadedFile::fake()->create('release.zip', 10, 'application/zip'),
                'title' => '1.0.0',
                'type' => 'beta',
                'changelog' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Первый релиз.'],
                            ],
                        ],
                    ],
                ],
                'dimension_value_ids' => [$value->id],
            ])
            ->assertCreated()
            ->assertJsonPath('title', '1.0.0')
            ->assertJsonPath('type', 'beta')
            ->assertJsonPath('status', 'on_moderation')
            ->assertJsonPath('released_at', today()->toDateString())
            ->assertJsonPath('dimension_value_ids.0', $value->id)
            ->assertJson(fn ($json) => $json
                ->whereType('file_url', 'string')
                ->where('file_name', 'release.zip')
                ->etc());

        $release = ProjectRelease::query()->firstOrFail();

        $this->assertSame(ProjectReleaseStatus::ON_MODERATION, $release->status);
        $this->assertCount(1, $release->getMedia('release'));
        $this->assertDatabaseHas('project_release_dimension_values', [
            'project_release_id' => $release->id,
            'dimension_value_id' => $value->id,
        ]);

        $this
            ->actingAs($user)
            ->getJson("/api/projects/{$project->id}/releases")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '1.0.0')
            ->assertJsonPath('data.0.dimension_value_ids.0', $value->id);
    }

    public function test_project_release_changelog_must_contain_text(): void
    {
        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectPayload($user, $gameContentType));

        $this
            ->actingAs($user)
            ->postJson("/api/projects/{$project->id}/releases", [
                'file' => UploadedFile::fake()->create('release.zip', 10, 'application/zip'),
                'title' => '1.0.0',
                'type' => 'beta',
                'changelog' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph'],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['changelog']);
    }

    public function test_admin_can_search_active_users_and_invite_project_member(): void
    {
        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectPayload($user, $gameContentType));
        $candidate = User::query()->create([
            'username' => 'candidate',
            'email' => 'candidate@example.com',
            'password' => 'password',
            'status' => CommonStatus::ACTIVE,
        ]);
        $candidate->userProfile()->create([
            'display_name' => 'Project Candidate',
        ]);
        $blocked = User::query()->create([
            'username' => 'blocked-candidate',
            'email' => 'blocked-candidate@example.com',
            'password' => 'password',
            'status' => CommonStatus::BLOCKED,
        ]);
        $blocked->userProfile()->create([
            'display_name' => 'Blocked Candidate',
        ]);

        $this
            ->actingAs($user)
            ->getJson("/api/projects/{$project->id}/member-candidates?search=Candidate")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $candidate->id)
            ->assertJsonPath('data.0.display_name', 'Project Candidate');

        $this
            ->actingAs($user)
            ->postJson("/api/projects/{$project->id}/members", [
                'user_id' => $candidate->id,
            ])
            ->assertCreated()
            ->assertJsonPath('user_id', $candidate->id)
            ->assertJsonPath('role', 'member')
            ->assertJsonPath('status', 'invited')
            ->assertJsonPath('display_name', 'Project Candidate');

        $member = ProjectMember::query()->firstOrFail();

        $this->assertSame(ProjectMemberRole::MEMBER, $member->role);
        $this->assertSame(MembershipStatus::INVITED, $member->status);

        $this
            ->actingAs($user)
            ->getJson("/api/projects/{$project->id}/members")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $candidate->id);

        $this
            ->actingAs($user)
            ->postJson("/api/projects/{$project->id}/members", [
                'user_id' => $candidate->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_admin_can_delete_project_release(): void
    {
        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectPayload($user, $gameContentType));
        $release = ProjectRelease::query()->create([
            'project_id' => $project->id,
            'title' => '1.0.0',
            'slug' => '1-0-0',
            'type' => 'release',
            'status' => 'on_moderation',
            'changelog' => [
                'type' => 'doc',
                'content' => [],
            ],
            'released_at' => today(),
        ]);

        $this
            ->actingAs($user)
            ->deleteJson("/api/projects/{$project->id}/releases/{$release->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Релиз удален.');

        $this->assertModelMissing($release);
    }

    public function test_project_release_delete_rejects_foreign_release(): void
    {
        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectPayload($user, $gameContentType));
        $anotherProject = Project::query()->create([
            ...$this->projectPayload($user, $gameContentType),
            'title' => 'Another Media Project',
        ]);
        $foreignRelease = ProjectRelease::query()->create([
            'project_id' => $anotherProject->id,
            'title' => '1.0.0',
            'slug' => '1-0-0',
            'type' => 'release',
            'status' => 'on_moderation',
            'changelog' => [
                'type' => 'doc',
                'content' => [],
            ],
            'released_at' => today(),
        ]);

        $this
            ->actingAs($user)
            ->deleteJson("/api/projects/{$project->id}/releases/{$foreignRelease->id}")
            ->assertNotFound();

        $this->assertModelExists($foreignRelease);
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

    public function test_admin_can_reorder_project_screenshots(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectPayload($user, $gameContentType));
        $firstScreenshot = $project->addMedia(UploadedFile::fake()->image('first.png', 1280, 720))
            ->toMediaCollection('screenshots');
        $secondScreenshot = $project->addMedia(UploadedFile::fake()->image('second.png', 1280, 720))
            ->toMediaCollection('screenshots');
        $thirdScreenshot = $project->addMedia(UploadedFile::fake()->image('third.png', 1280, 720))
            ->toMediaCollection('screenshots');

        $this
            ->actingAs($user)
            ->patchJson("/api/projects/{$project->id}/screenshots/order", [
                'media_ids' => [$thirdScreenshot->id, $firstScreenshot->id, $secondScreenshot->id],
            ])
            ->assertOk()
            ->assertJsonPath('screenshots.0.id', $thirdScreenshot->id)
            ->assertJsonPath('screenshots.1.id', $firstScreenshot->id)
            ->assertJsonPath('screenshots.2.id', $secondScreenshot->id);

        $this->assertSame(
            [$thirdScreenshot->id, $firstScreenshot->id, $secondScreenshot->id],
            $project->refresh()->getMedia('screenshots')->pluck('id')->all()
        );
    }

    public function test_screenshot_reorder_requires_exact_project_screenshot_ids(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectPayload($user, $gameContentType));
        $firstScreenshot = $project->addMedia(UploadedFile::fake()->image('first.png', 1280, 720))
            ->toMediaCollection('screenshots');
        $secondScreenshot = $project->addMedia(UploadedFile::fake()->image('second.png', 1280, 720))
            ->toMediaCollection('screenshots');
        $anotherProject = Project::query()->create([
            ...$this->projectPayload($user, $gameContentType),
            'title' => 'Another Media Project',
        ]);
        $foreignScreenshot = $anotherProject
            ->addMedia(UploadedFile::fake()->image('foreign.png', 1280, 720))
            ->toMediaCollection('screenshots');

        $this
            ->actingAs($user)
            ->patchJson("/api/projects/{$project->id}/screenshots/order", [
                'media_ids' => [$secondScreenshot->id, $foreignScreenshot->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['media_ids']);

        $this->assertSame(
            [$firstScreenshot->id, $secondScreenshot->id],
            $project->refresh()->getMedia('screenshots')->pluck('id')->all()
        );
    }

    public function test_admin_can_delete_project_screenshot(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectPayload($user, $gameContentType));
        $firstScreenshot = $project->addMedia(UploadedFile::fake()->image('first.png', 1280, 720))
            ->toMediaCollection('screenshots');
        $secondScreenshot = $project->addMedia(UploadedFile::fake()->image('second.png', 1280, 720))
            ->toMediaCollection('screenshots');

        $this
            ->actingAs($user)
            ->deleteJson("/api/projects/{$project->id}/screenshots/{$firstScreenshot->id}")
            ->assertOk()
            ->assertJsonCount(1, 'screenshots')
            ->assertJsonPath('screenshots.0.id', $secondScreenshot->id);

        $this->assertDatabaseMissing('media', ['id' => $firstScreenshot->id]);
        $this->assertSame(
            [$secondScreenshot->id],
            $project->refresh()->getMedia('screenshots')->pluck('id')->all()
        );
    }

    public function test_project_screenshot_delete_rejects_foreign_media(): void
    {
        Storage::fake('public');

        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create($this->projectPayload($user, $gameContentType));
        $anotherProject = Project::query()->create([
            ...$this->projectPayload($user, $gameContentType),
            'title' => 'Another Media Project',
        ]);
        $foreignScreenshot = $anotherProject
            ->addMedia(UploadedFile::fake()->image('foreign.png', 1280, 720))
            ->toMediaCollection('screenshots');

        $this
            ->actingAs($user)
            ->deleteJson("/api/projects/{$project->id}/screenshots/{$foreignScreenshot->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('media', ['id' => $foreignScreenshot->id]);
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

    public function test_failed_project_step_update_rolls_back_database_changes_and_cleans_new_media(): void
    {
        Storage::fake('public');

        $this->app->bind(SaveProjectStep::class, FailingAfterScreenshotsSaveProjectStep::class);

        [$user, $gameContentType] = $this->projectContext();
        $project = Project::query()->create([
            ...$this->projectPayload($user, $gameContentType),
            'title' => 'Original Project',
            'percentage_complete' => 20,
        ]);
        $oldLogo = $project
            ->addMedia(UploadedFile::fake()->image('old-logo.png', 256, 256))
            ->toMediaCollection('logo');

        $this
            ->actingAs($user)
            ->post("/api/projects/{$project->id}", [
                '_method' => 'PATCH',
                'title' => 'Updated Project',
                'percentage_complete' => 40,
                'screenshots' => [
                    UploadedFile::fake()->image('new-screenshot.png', 1280, 720),
                ],
                'logo' => UploadedFile::fake()->image('new-logo.png', 512, 512),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertServerError();

        $project->refresh();

        $this->assertSame('Original Project', $project->title);
        $this->assertSame(20, $project->percentage_complete);
        $this->assertCount(1, $project->getMedia('logo'));
        $this->assertSame($oldLogo->id, $project->getFirstMedia('logo')?->id);
        $this->assertCount(0, $project->getMedia('screenshots'));
        $this->assertDatabaseCount('media', 1);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_failed_first_project_step_does_not_leave_partial_draft_or_media(): void
    {
        Storage::fake('public');

        $this->app->bind(SaveProjectStep::class, FailingAfterScreenshotsSaveProjectStep::class);

        [$user, $gameContentType] = $this->projectContext();

        $this
            ->actingAs($user)
            ->post('/api/projects', [
                ...$this->projectPayload($user, $gameContentType),
                'logo' => UploadedFile::fake()->image('logo.png', 512, 512),
                'screenshots' => [
                    UploadedFile::fake()->image('screenshot.png', 1280, 720),
                ],
            ], [
                'Accept' => 'application/json',
            ])
            ->assertServerError();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('media', 0);
        $this->assertCount(0, Storage::disk('public')->allFiles());
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
            'status' => 'draft',
        ];
    }
}

class FailingAfterScreenshotsSaveProjectStep extends SaveProjectStep
{
    protected function replaceLogo(Project $project, ?UploadedFile $logo, array &$createdMedia): void
    {
        throw new RuntimeException('Simulated step save failure.');
    }
}
