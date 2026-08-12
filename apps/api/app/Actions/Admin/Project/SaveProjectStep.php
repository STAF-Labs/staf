<?php

namespace App\Actions\Admin\Project;

use App\Models\Game\Project\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class SaveProjectStep
{
    /**
     * @param  array<string, mixed>  $validated
     * @param  list<UploadedFile>  $screenshots
     */
    public function execute(
        array $validated,
        ?Project $project = null,
        ?UploadedFile $logo = null,
        array $screenshots = [],
    ): Project {
        $createdMedia = [];

        try {
            return DB::transaction(function () use (
                $validated,
                $project,
                $logo,
                $screenshots,
                &$createdMedia
            ): Project {
                $project = $this->saveProject($validated, $project);

                $this->syncProjectDimensionValues($project, $validated);
                $this->addScreenshots($project, $screenshots, $createdMedia);
                $this->replaceLogo($project, $logo, $createdMedia);

                return $project->refresh();
            });
        } catch (Throwable $exception) {
            $this->deleteCreatedMedia($createdMedia);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function saveProject(array $validated, ?Project $project): Project
    {
        $attributes = Arr::except($validated, ['logo', 'screenshots', 'dimension_value_ids']);

        if ($project === null) {
            return Project::query()->create($attributes);
        }

        $project->update($attributes);

        return $project;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function syncProjectDimensionValues(Project $project, array $validated): void
    {
        if (! array_key_exists('dimension_value_ids', $validated)) {
            return;
        }

        $project->dimensionValues()->sync($validated['dimension_value_ids'] ?? []);
    }

    /**
     * @param  list<UploadedFile>  $screenshots
     * @param  list<Media>  $createdMedia
     */
    protected function addScreenshots(Project $project, array $screenshots, array &$createdMedia): void
    {
        foreach ($screenshots as $screenshot) {
            $createdMedia[] = $project->addMedia($screenshot)->toMediaCollection('screenshots');
        }
    }

    /**
     * @param  list<Media>  $createdMedia
     */
    protected function replaceLogo(Project $project, ?UploadedFile $logo, array &$createdMedia): void
    {
        if ($logo === null) {
            return;
        }

        $createdMedia[] = $project->addMedia($logo)->toMediaCollection('logo');
    }

    /**
     * @param  list<Media>  $createdMedia
     */
    private function deleteCreatedMedia(array $createdMedia): void
    {
        foreach ($createdMedia as $media) {
            $media->delete();
        }
    }
}
