<?php

namespace App\Http\Controllers\Api\Admin\Org;

use App\Enums\CommonStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Org\OrganizationResource;
use App\Models\Org\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /**
         * @var array{
         *     search?: string|null,
         *     status?: string|null,
         *     is_visible?: string|null,
         *     verification?: string|null,
         *     created_from?: string|null,
         *     created_to?: string|null,
         *     deleted?: string|null
         * } $filters
         */
        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(CommonStatus::class)],
            'is_visible' => ['nullable', 'in:0,1'],
            'verification' => ['nullable', 'in:verified,unverified'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d'],
            'deleted' => ['nullable', 'in:all,only,without'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $isVisible = $filters['is_visible'] ?? null;
        $verification = $filters['verification'] ?? null;
        $createdFrom = $filters['created_from'] ?? null;
        $createdTo = $filters['created_to'] ?? null;
        $deleted = $filters['deleted'] ?? 'without';

        $query = Organization::query()
            ->with('media')
            ->when($deleted === 'all', function (Builder $query): void {
                $query->withTrashed();
            })
            ->when($deleted === 'only', function (Builder $query): void {
                $query->onlyTrashed();
            })
            ->when($status !== null, function (Builder $query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($isVisible !== null, function (Builder $query) use ($isVisible): void {
                $query->where('is_visible', $isVisible === '1');
            })
            ->when($verification === 'verified', function (Builder $query): void {
                $query->whereNotNull('verified_at');
            })
            ->when($verification === 'unverified', function (Builder $query): void {
                $query->whereNull('verified_at');
            })
            ->when($createdFrom !== null, function (Builder $query) use ($createdFrom): void {
                $query->whereDate('created_at', '>=', $createdFrom);
            })
            ->when($createdTo !== null, function (Builder $query) use ($createdTo): void {
                $query->whereDate('created_at', '<=', $createdTo);
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%");
                });
            });

        $filteredTotal = (clone $query)->count();

        return response()->json([
            'data' => OrganizationResource::collection(
                $query
                    ->latest('id')
                    ->get()
            )->resolve($request),
            'total' => Organization::query()->count(),
            'filtered_total' => $filteredTotal,
        ]);
    }

    public function block(Request $request, Organization $organization): JsonResponse
    {
        $organization->forceFill([
            'status' => CommonStatus::BLOCKED,
        ])->save();

        return response()->json(
            OrganizationResource::make($organization)->resolve($request)
        );
    }

    public function show(Request $request, Organization $organization): JsonResponse
    {
        return response()->json(
            OrganizationResource::make($organization->load(['media', 'orgMembers.member']))->resolve($request)
        );
    }

    public function unblock(Request $request, Organization $organization): JsonResponse
    {
        $organization->forceFill([
            'status' => CommonStatus::ACTIVE,
        ])->save();

        return response()->json(
            OrganizationResource::make($organization)->resolve($request)
        );
    }

    public function freeze(Request $request, Organization $organization): JsonResponse
    {
        $organization->forceFill([
            'status' => CommonStatus::SUSPENDED,
        ])->save();

        return response()->json(
            OrganizationResource::make($organization)->resolve($request)
        );
    }

    public function unfreeze(Request $request, Organization $organization): JsonResponse
    {
        $organization->forceFill([
            'status' => CommonStatus::ACTIVE,
        ])->save();

        return response()->json(
            OrganizationResource::make($organization)->resolve($request)
        );
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $organization->delete();

        return response()->json([
            'message' => 'Организация удалена.',
        ]);
    }
}
