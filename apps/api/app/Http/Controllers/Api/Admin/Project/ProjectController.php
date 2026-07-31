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

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Project::query()
            ->with('gameContentType.game', 'gameContentType.contentType')
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
                $project->load('gameContentType.game', 'gameContentType.contentType')
                    ->loadMax('releases', 'released_at')
            )->resolve($request)
        );
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::create($request->validated());

        return response()->json(
            ProjectResource::make(
                $project->load('gameContentType.game', 'gameContentType.contentType')
                    ->loadMax('releases', 'released_at')
            )->resolve($request),
            201
        );
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $project->update($request->validated());

        return response()->json(
            ProjectResource::make(
                $project->load('gameContentType.game', 'gameContentType.contentType')
                    ->loadMax('releases', 'released_at')
            )->resolve($request)
        );
    }
}
