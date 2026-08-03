<?php

namespace Tests\Feature\Admin\Game;

use App\Models\Game\ContentType\ContentType;
use App\Models\Game\Game;
use App\Models\Game\Project\Project;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DimensionAssignmentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_and_release_values_use_separate_relations(): void
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
        $category = $gameContentType->dimensions()->create([
            'name' => 'Категория',
            'applies_to' => 'project',
            'selection_mode' => 'single',
        ]);
        $categoryValue = $category->values()->create(['name' => 'Геймплей']);
        $gameVersion = $gameContentType->dimensions()->create([
            'name' => 'Версия игры',
            'applies_to' => 'release',
            'selection_mode' => 'single',
        ]);
        $gameVersionValue = $gameVersion->values()->create(['name' => '1.21']);
        $project = Project::query()->create([
            'ownerable_type' => User::class,
            'ownerable_id' => $user->id,
            'game_content_type_id' => $gameContentType->id,
            'title' => 'Project',
            'description' => [],
            'status' => 'draft',
        ]);
        $release = $project->releases()->create([
            'title' => '1.0.0',
            'changelog' => [],
            'released_at' => '2026-08-03',
            'status' => 'draft',
        ]);

        $project->dimensionValues()->attach($categoryValue->id);
        $release->dimensionValues()->attach($gameVersionValue->id);

        $this->assertTrue($project->fresh()->dimensionValues->contains($categoryValue));
        $this->assertTrue($release->fresh()->dimensionValues->contains($gameVersionValue));
        $this->assertSame(1, $category->projectSelections()->count());
        $this->assertSame(0, $category->releaseSelections()->count());
        $this->assertSame(0, $gameVersion->projectSelections()->count());
        $this->assertSame(1, $gameVersion->releaseSelections()->count());
    }
}
