<?php

namespace App\Http\Controllers\Api\Admin\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Game\StoreGameContentTypeRequest;
use App\Http\Resources\Game\ContentType\GameContentTypeResource;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameContentTypeController extends Controller
{
    public function index(Request $request, Game $game): JsonResponse
    {
        $gameContentTypes = $game->gameContentTypes()
            ->with('contentType')
            ->withCount('projects', 'dimensions')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => GameContentTypeResource::collection($gameContentTypes)->resolve($request),
            'total' => $gameContentTypes->count(),
            'filtered_total' => $gameContentTypes->count(),
        ]);
    }

    public function store(StoreGameContentTypeRequest $request, Game $game): JsonResponse
    {
        $gameContentType = $game->gameContentTypes()->create($request->validated());

        return response()->json(
            GameContentTypeResource::make(
                $gameContentType->load('contentType')->loadCount('projects', 'dimensions')
            )->resolve($request),
            201
        );
    }

    public function destroy(Game $game, GameContentType $gameContentType): JsonResponse
    {
        if ($gameContentType->game_id !== $game->id) {
            abort(404);
        }

        if ($gameContentType->projects()->exists()) {
            return response()->json([
                'message' => 'Нельзя удалить тип контента, который используется в проектах.',
            ], 409);
        }

        $gameContentType->delete();

        return response()->json([
            'message' => 'Тип контента отключен от игры.',
        ]);
    }
}
