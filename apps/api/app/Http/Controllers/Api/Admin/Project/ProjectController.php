<?php

namespace App\Http\Controllers\Api\Admin\Project;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Project\StoreProjectRequest;
use App\Http\Requests\Admin\Project\UpdateProjectRequest;
use App\Http\Resources\Game\Project\ProjectResource;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Project\Project;
use App\Models\Org\OrganizationMember;
use App\Models\User\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Project::query()
            ->with('gameContentType.game', 'gameContentType.contentType', 'media')
            ->withMax('releases', 'released_at')
            ->latest('id');

        return response()->json([
            'data' => ProjectResource::collection($query->get())->resolve($request),
            'total' => Project::query()->count(),
            'filtered_total' => Project::query()->count(),
        ]);
    }

    public function contentTypes(): JsonResponse
    {
        $contentTypes = GameContentType::query()
            ->with(['game', 'contentType'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $contentTypes->map(fn (GameContentType $gameContentType): array => [
                'id' => $gameContentType->id,
                'game_id' => $gameContentType->game_id,
                'game_name' => $gameContentType->game?->name,
                'content_type_id' => $gameContentType->content_type_id,
                'content_type_name' => $gameContentType->contentType?->name,
            ]),
            'total' => $contentTypes->count(),
            'filtered_total' => $contentTypes->count(),
        ]);
    }

    public function ownerOptions(Request $request): JsonResponse
    {
        /**
         * @var User&Authenticatable $user
         */
        $user = $request->user();

        $organizationOptions = OrganizationMember::query()
            ->with('inOrganization')
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::ACTIVE)
            ->get()
            ->map(fn (OrganizationMember $membership): ?array => $membership->inOrganization === null ? null : [
                'type' => $membership->inOrganization::class,
                'id' => $membership->inOrganization->id,
                'label' => $membership->inOrganization->name,
            ])
            ->filter()
            ->values();

        return response()->json([
            'data' => collect([
                [
                    'type' => $user::class,
                    'id' => $user->id,
                    'label' => $user->username,
                ],
            ])->concat($organizationOptions)->values(),
        ]);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        return response()->json(
            ProjectResource::make(
                $project->load('gameContentType.game', 'gameContentType.contentType', 'media')
                    ->loadMax('releases', 'released_at')
            )->resolve($request)
        );
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $project = Project::create(Arr::except(
            $validated,
            ['logo', 'screenshots', 'dimension_value_ids']
        ));

        $project->addMediaFromRequest('logo')->toMediaCollection('logo');

        foreach ($request->file('screenshots', []) as $screenshot) {
            $project->addMedia($screenshot)->toMediaCollection('screenshots');
        }

        $project->dimensionValues()->sync($validated['dimension_value_ids'] ?? []);

        return response()->json(
            ProjectResource::make(
                $project->load(
                    'gameContentType.game',
                    'gameContentType.contentType',
                    'media',
                    'dimensionValues'
                )
                    ->loadMax('releases', 'released_at')
            )->resolve($request),
            201
        );
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $project->update(Arr::except(
            $request->validated(),
            ['logo', 'screenshots']
        ));

        if ($request->hasFile('logo')) {
            $project->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        foreach ($request->file('screenshots', []) as $screenshot) {
            $project->addMedia($screenshot)->toMediaCollection('screenshots');
        }

        return response()->json(
            ProjectResource::make(
                $project->load('gameContentType.game', 'gameContentType.contentType', 'media')
                    ->loadMax('releases', 'released_at')
            )->resolve($request)
        );
    }
}
