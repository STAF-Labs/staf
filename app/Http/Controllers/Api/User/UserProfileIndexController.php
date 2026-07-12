<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserProfileResource;
use App\Models\User\UserProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileIndexController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();

        $query = UserProfile::query()
            ->with('user')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('display_name', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $query) use ($search): void {
                            $query
                                ->where('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });

        $filteredTotal = (clone $query)->count();

        return response()->json([
            'data' => UserProfileResource::collection(
                $query
                    ->latest('id')
                    ->get()
            )->resolve($request),
            'total' => UserProfile::query()->count(),
            'filtered_total' => $filteredTotal,
        ]);
    }
}
