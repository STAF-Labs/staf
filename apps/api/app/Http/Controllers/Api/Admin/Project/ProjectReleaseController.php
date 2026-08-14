<?php

namespace App\Http\Controllers\Api\Admin\Project;

use App\Actions\Admin\Project\SaveProjectRelease;
use App\Enums\Project\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Project\StoreProjectReleaseRequest;
use App\Http\Requests\Admin\Project\UpdateProjectReleaseRequest;
use App\Http\Resources\Game\Project\ProjectReleaseResource;
use App\Models\Game\Project\Project;
use App\Models\Game\Project\ProjectRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectReleaseController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $releases = $project->releases()
            ->with('dimensionValues', 'media')
            ->latest('released_at')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => ProjectReleaseResource::collection($releases)->resolve($request),
            'total' => $releases->count(),
            'filtered_total' => $releases->count(),
        ]);
    }

    public function show(Request $request, Project $project, ProjectRelease $release): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $project);

        abort_if($release->project_id !== $project->id, 404);

        return response()->json(
            ProjectReleaseResource::make(
                $release->load('media', 'dimensionValues')
            )->resolve($request)
        );
    }

    public function store(
        StoreProjectReleaseRequest $request,
        Project $project,
        SaveProjectRelease $saveProjectRelease
    ): JsonResponse
    {
        $this->ensureProjectCanHaveReleases($project);

        $release = $saveProjectRelease->execute(
            $project,
            $request->validated(),
            file: $request->file('file')
        );

        return response()->json(
            ProjectReleaseResource::make(
                $release->load('media', 'dimensionValues')
            )->resolve($request),
            201
        );
    }

    public function update(
        UpdateProjectReleaseRequest $request,
        Project $project,
        ProjectRelease $release,
        SaveProjectRelease $saveProjectRelease
    ): JsonResponse {
        abort_if($release->project_id !== $project->id, 404);

        $this->ensureProjectCanHaveReleases($project);

        $release = $saveProjectRelease->execute(
            $project,
            $request->validated(),
            $release,
            $request->file('file')
        );

        return response()->json(
            ProjectReleaseResource::make(
                $release->load('media', 'dimensionValues')
            )->resolve($request)
        );
    }

    public function destroy(Request $request, Project $project, ProjectRelease $release): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $project);

        abort_if($release->project_id !== $project->id, 404);

        $release->delete();

        return response()->json([
            'message' => 'Релиз удален.',
        ]);
    }

    private function ensureProjectCanHaveReleases(Project $project): void
    {
        abort_if(
            $project->status !== ProjectStatus::PUBLISHED,
            409,
            'Релизы можно добавлять только к опубликованному проекту.'
        );
    }
}
