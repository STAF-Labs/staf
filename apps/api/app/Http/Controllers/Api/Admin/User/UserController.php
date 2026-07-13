<?php

namespace App\Http\Controllers\Api\Admin\User;

use App\Enums\CommonStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserDetailResource;
use App\Http\Resources\User\UserResource;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /**
         * @var array{
         *     search?: string|null,
         *     status?: string|null,
         *     created_from?: string|null,
         *     created_to?: string|null,
         *     is_public?: string|null,
         *     show_online_status?: string|null,
         *     show_last_seen_at?: string|null,
         *     deleted?: string|null
         * } $filters
         */
        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(CommonStatus::class)],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d'],
            'is_public' => ['nullable', 'in:0,1'],
            'show_online_status' => ['nullable', 'in:0,1'],
            'show_last_seen_at' => ['nullable', 'in:0,1'],
            'deleted' => ['nullable', 'in:all,only,without'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $createdFrom = $filters['created_from'] ?? null;
        $createdTo = $filters['created_to'] ?? null;
        $isPublic = $filters['is_public'] ?? null;
        $showOnlineStatus = $filters['show_online_status'] ?? null;
        $showLastSeenAt = $filters['show_last_seen_at'] ?? null;
        $deleted = $filters['deleted'] ?? 'without';

        $query = User::query()
            ->with('userProfile.media')
            ->when($deleted === 'all', function (Builder $query): void {
                $query->withTrashed();
            })
            ->when($deleted === 'only', function (Builder $query): void {
                $query->onlyTrashed();
            })
            ->when($status !== null, function (Builder $query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($createdFrom !== null, function (Builder $query) use ($createdFrom): void {
                $query->whereDate('created_at', '>=', $createdFrom);
            })
            ->when($createdTo !== null, function (Builder $query) use ($createdTo): void {
                $query->whereDate('created_at', '<=', $createdTo);
            })
            ->when($isPublic !== null, function (Builder $query) use ($isPublic): void {
                $query->whereHas('userProfile', function (Builder $query) use ($isPublic): void {
                    $query->where('is_public', $isPublic === '1');
                });
            })
            ->when($showOnlineStatus !== null, function (Builder $query) use ($showOnlineStatus): void {
                $query->whereHas('userProfile', function (Builder $query) use ($showOnlineStatus): void {
                    $query->where('show_online_status', $showOnlineStatus === '1');
                });
            })
            ->when($showLastSeenAt !== null, function (Builder $query) use ($showLastSeenAt): void {
                $query->whereHas('userProfile', function (Builder $query) use ($showLastSeenAt): void {
                    $query->where('show_last_seen_at', $showLastSeenAt === '1');
                });
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('userProfile', function (Builder $query) use ($search): void {
                            $query->where('display_name', 'like', "%{$search}%");
                        });
                });
            });

        $filteredTotal = (clone $query)->count();

        return response()->json([
            'data' => UserResource::collection(
                $query
                    ->latest('id')
                    ->get()
            )->resolve($request),
            'total' => User::query()->count(),
            'filtered_total' => $filteredTotal,
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        return response()->json(
            UserDetailResource::make($user->load(['memberOf.inOrganization', 'userProfile.media']))->resolve($request)
        );
    }

    public function block(Request $request, User $user): JsonResponse
    {
        $user->forceFill([
            'status' => CommonStatus::BLOCKED,
        ])->save();

        return response()->json(
            UserDetailResource::make($user->load(['memberOf.inOrganization', 'userProfile.media']))->resolve($request)
        );
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        $user->forceFill([
            'status' => CommonStatus::ACTIVE,
        ])->save();

        return response()->json(
            UserDetailResource::make($user->load(['memberOf.inOrganization', 'userProfile.media']))->resolve($request)
        );
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'Пользователь удален.',
        ]);
    }
}
