<?php

namespace App\Actions\Admin\Project;

use App\Enums\Project\ProjectReleaseStatus;
use App\Models\Game\Project\Project;
use App\Models\Game\Project\ProjectRelease;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveProjectRelease
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(
        Project $project,
        array $validated,
        ?ProjectRelease $release = null,
        ?UploadedFile $file = null,
    ): ProjectRelease {
        return DB::transaction(function () use ($project, $validated, $release, $file): ProjectRelease {
            $release = $this->saveRelease($project, $validated, $release);

            if ($file !== null) {
                $release->addMedia($file)->toMediaCollection('release');
            }

            $release->dimensionValues()->sync($validated['dimension_value_ids'] ?? []);

            return $release->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function saveRelease(Project $project, array $validated, ?ProjectRelease $release): ProjectRelease
    {
        $attributes = Arr::except($validated, ['file', 'dimension_value_ids']);

        if ($release === null) {
            return $project->releases()->create([
                ...$attributes,
                'released_at' => today()->toDateString(),
                'status' => ProjectReleaseStatus::ON_MODERATION->value,
            ]);
        }

        $release->update($attributes);

        return $release;
    }
}
