<?php

namespace App\Http\Controllers\Api\Admin\Project;

use App\Enums\Project\ProjectReleaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Project\StoreProjectReleaseRequest;
use App\Http\Resources\Game\Project\ProjectReleaseResource;
use App\Models\Game\Project\Project;
use App\Models\Game\Project\ProjectRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

    public function store(StoreProjectReleaseRequest $request, Project $project): JsonResponse
    {
        $validated = $request->validated();

        $release = $project->releases()->create([
            ...Arr::except($validated, ['file', 'dimension_value_ids']),
            'released_at' => today()->toDateString(),
            'status' => ProjectReleaseStatus::ON_MODERATION->value,
        ]);

        $release->addMediaFromRequest('file')->toMediaCollection('release');
        $release->dimensionValues()->sync($validated['dimension_value_ids'] ?? []);

        return response()->json(
            ProjectReleaseResource::make(
                $release->load('media', 'dimensionValues')
            )->resolve($request),
            201
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
}
