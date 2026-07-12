<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserIndexController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();

        $query = User::query()
            ->with('userProfile')
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
}
