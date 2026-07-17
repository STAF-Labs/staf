<?php

namespace App\Http\Controllers\Api\Admin\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Game\StoreGameRequest;
use App\Http\Requests\Admin\Game\UpdateGameRequest;
use App\Http\Resources\Game\GameResource;
use App\Models\Game\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Game::query()->with('media')->latest('id');

        return response()->json([
            'data' => GameResource::collection($query->get())->resolve($request),
            'total' => Game::query()->count(),
            'filtered_total' => Game::query()->count(),
        ]);
    }

    public function show(Request $request, Game $game): JsonResponse
    {
        return response()->json(
            GameResource::make($game->load('media'))->resolve($request)
        );
    }

    public function store(StoreGameRequest $request): JsonResponse
    {
        /**
         * @var array{
         *     name: string,
         *     description?: string|null,
         *     released_at?: string|null,
         *     status: string
         * } $validated
         */
        $validated = $request->validated();

        $game = Game::create([
            'name' => $validated['name'],
            'description' => isset($validated['description'])
                ? json_decode($validated['description'], true)
                : null,
            'released_at' => $validated['released_at'] ?? null,
            'status' => $validated['status'],
        ]);

        $game->addMediaFromRequest('banner')->toMediaCollection('banner');

        if ($request->hasFile('logo')) {
            $game->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        return response()->json(
            GameResource::make($game->load('media'))->resolve($request),
            201
        );
    }

    public function update(UpdateGameRequest $request, Game $game): JsonResponse
    {
        /**
         * @var array{
         *     name: string,
         *     description?: string|null,
         *     released_at?: string|null,
         *     status: string
         * } $validated
         */
        $validated = $request->validated();

        $game->update([
            'name' => $validated['name'],
            'description' => isset($validated['description'])
                ? json_decode($validated['description'], true)
                : null,
            'released_at' => $validated['released_at'] ?? null,
            'status' => $validated['status'],
        ]);

        if ($request->hasFile('banner')) {
            $game->addMediaFromRequest('banner')->toMediaCollection('banner');
        }

        if ($request->hasFile('logo')) {
            $game->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        return response()->json(
            GameResource::make($game->load('media'))->resolve($request)
        );
    }

    public function destroy(Game $game): JsonResponse
    {
        $game->delete();

        return response()->json([
            'message' => 'Игра удалена.',
        ]);
    }
}
