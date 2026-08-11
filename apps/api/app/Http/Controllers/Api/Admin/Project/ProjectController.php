<?php

namespace App\Http\Controllers\Api\Admin\Project;

use App\Enums\CommonStatus;
use App\Enums\MembershipStatus;
use App\Enums\Org\OrgMemberRole;
use App\Enums\Project\ProjectMemberRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Project\ReorderProjectScreenshotsRequest;
use App\Http\Requests\Admin\Project\StoreProjectMemberRequest;
use App\Http\Requests\Admin\Project\StoreProjectRequest;
use App\Http\Requests\Admin\Project\UpdateProjectRequest;
use App\Http\Resources\Game\Filter\DimensionResource;
use App\Http\Resources\Game\Project\ProjectMemberResource;
use App\Http\Resources\Game\Project\ProjectResource;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Project\Project;
use App\Models\Game\Project\ProjectMember;
use App\Models\Org\Organization;
use App\Models\Org\OrganizationMember;
use App\Models\User\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Project::query()
            ->with([
                'ownerable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    User::class => ['userProfile'],
                ]),
                'gameContentType.game',
                'gameContentType.contentType',
                'media',
            ])
            ->withCount('releases')
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

        Gate::forUser($user)->authorize('create', [Project::class, $user]);

        $organizationOptions = OrganizationMember::query()
            ->with('inOrganization')
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::ACTIVE)
            ->whereIn('role', [OrgMemberRole::OWNER, OrgMemberRole::MAINTAINER])
            ->whereHas('inOrganization', fn ($query) => $query->where('status', CommonStatus::ACTIVE))
            ->get()
            ->filter(fn (OrganizationMember $membership): bool => $membership->inOrganization !== null
                && Gate::forUser($user)->allows('create', [Project::class, $membership->inOrganization]))
            ->map(fn (OrganizationMember $membership): ?array => $membership->inOrganization === null ? null : [
                'kind' => 'organization',
                'type' => $membership->inOrganization::class,
                'id' => $membership->inOrganization->id,
                'label' => $membership->inOrganization->name,
            ])
            ->filter()
            ->values();

        return response()->json([
            'data' => collect([
                [
                    'kind' => 'user',
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
                $project->load(
                    'ownerable',
                    'gameContentType.game',
                    'gameContentType.contentType',
                    'media',
                    'dimensionValues'
                )
                    ->loadMorph('ownerable', [
                        User::class => ['userProfile'],
                    ])
                    ->loadCount('releases')
                    ->loadMax('releases', 'released_at')
            )->resolve($request)
        );
    }

    public function releaseFilters(Request $request, Project $project): JsonResponse
    {
        $dimensions = $project->gameContentType()
            ->firstOrFail()
            ->dimensions()
            ->where('applies_to', 'release')
            ->where('is_active', true)
            ->where('is_filterable', true)
            ->with(['values' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => DimensionResource::collection($dimensions)->resolve($request),
            'total' => $dimensions->count(),
            'filtered_total' => $dimensions->count(),
        ]);
    }

    public function members(Request $request, Project $project): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $project);

        $members = $project->members()
            ->with('user.userProfile.media')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => ProjectMemberResource::collection($members)->resolve($request),
            'total' => $members->count(),
            'filtered_total' => $members->count(),
        ]);
    }

    public function memberCandidates(Request $request, Project $project): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $project);

        $validated = $request->validate([
            'search' => ['nullable', 'string'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $excludedUserIds = $project->members()->pluck('user_id');

        if ($project->ownerable_type === User::class) {
            $excludedUserIds->push((int) $project->ownerable_id);
        }

        $query = User::query()
            ->with('userProfile.media')
            ->where('status', CommonStatus::ACTIVE)
            ->whereNotIn('id', $excludedUserIds->unique()->values())
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('username', 'like', "%{$search}%")
                        ->orWhereHas('userProfile', function (Builder $query) use ($search): void {
                            $query->where('display_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('username')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => $query->map(fn (User $user): array => [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->userProfile?->display_name,
                'avatar_url' => $user->userProfile?->getFirstMediaUrl('avatar') ?: null,
            ])->values(),
            'total' => $query->count(),
            'filtered_total' => $query->count(),
        ]);
    }

    public function storeMember(StoreProjectMemberRequest $request, Project $project): JsonResponse
    {
        $member = $project->members()->create([
            'user_id' => $request->integer('user_id'),
            'role' => ProjectMemberRole::MEMBER->value,
            'status' => MembershipStatus::INVITED->value,
        ]);

        return response()->json(
            ProjectMemberResource::make(
                $member->load('user.userProfile.media')
            )->resolve($request),
            201
        );
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $author = match ($validated['ownerable_type']) {
            User::class => User::query()->findOrFail($validated['ownerable_id']),
            Organization::class => Organization::query()->findOrFail($validated['ownerable_id']),
        };

        Gate::forUser($request->user())->authorize('create', [Project::class, $author]);

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
                    'ownerable',
                    'gameContentType.game',
                    'gameContentType.contentType',
                    'media',
                    'dimensionValues'
                )
                    ->loadMorph('ownerable', [
                        User::class => ['userProfile'],
                    ])
                    ->loadCount('releases')
                    ->loadMax('releases', 'released_at')
            )->resolve($request),
            201
        );
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $validated = $request->validated();
        $project->update(Arr::except(
            $validated,
            ['logo', 'screenshots', 'dimension_value_ids']
        ));

        if ($request->hasFile('logo')) {
            $project->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        foreach ($request->file('screenshots', []) as $screenshot) {
            $project->addMedia($screenshot)->toMediaCollection('screenshots');
        }

        if (array_key_exists('dimension_value_ids', $validated)) {
            $project->dimensionValues()->sync($validated['dimension_value_ids'] ?? []);
        }

        return response()->json(
            ProjectResource::make(
                $project->load(
                    'ownerable',
                    'gameContentType.game',
                    'gameContentType.contentType',
                    'media',
                    'dimensionValues'
                )
                    ->loadMorph('ownerable', [
                        User::class => ['userProfile'],
                    ])
                    ->loadCount('releases')
                    ->loadMax('releases', 'released_at')
            )->resolve($request)
        );
    }

    public function reorderScreenshots(
        ReorderProjectScreenshotsRequest $request,
        Project $project
    ): JsonResponse {
        Media::setNewOrder($request->validated()['media_ids']);

        return response()->json(
            ProjectResource::make(
                $project->refresh()->load(
                    'ownerable',
                    'gameContentType.game',
                    'gameContentType.contentType',
                    'media',
                    'dimensionValues'
                )
                    ->loadMorph('ownerable', [
                        User::class => ['userProfile'],
                    ])
                    ->loadCount('releases')
                    ->loadMax('releases', 'released_at')
            )->resolve($request)
        );
    }

    public function destroyScreenshot(Request $request, Project $project, Media $media): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $project);

        abort_if(
            $media->model_type !== $project->getMorphClass()
            || (int) $media->model_id !== $project->id
            || $media->collection_name !== 'screenshots',
            404
        );

        $media->delete();

        return response()->json(
            ProjectResource::make(
                $project->refresh()->load(
                    'ownerable',
                    'gameContentType.game',
                    'gameContentType.contentType',
                    'media',
                    'dimensionValues'
                )
                    ->loadMorph('ownerable', [
                        User::class => ['userProfile'],
                    ])
                    ->loadCount('releases')
                    ->loadMax('releases', 'released_at')
            )->resolve($request)
        );
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        Gate::forUser($request->user())->authorize('delete', $project);

        $project->delete();

        return response()->json([
            'message' => 'Проект удален.',
        ]);
    }
}
