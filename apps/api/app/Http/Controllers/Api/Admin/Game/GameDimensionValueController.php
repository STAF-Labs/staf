<?php

namespace App\Http\Controllers\Api\Admin\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Game\StoreDimensionValueRequest;
use App\Http\Requests\Admin\Game\UpdateDimensionValueRequest;
use App\Http\Resources\Game\Filter\DimensionValueResource;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Filter\Dimension;
use App\Models\Game\Filter\DimensionValue;
use App\Models\Game\Game;
use Illuminate\Http\JsonResponse;

class GameDimensionValueController extends Controller
{
    public function store(
        StoreDimensionValueRequest $request,
        Game $game,
        GameContentType $gameContentType,
        Dimension $dimension
    ): JsonResponse {
        $this->ensureDimensionBelongsToContext($game, $gameContentType, $dimension);

        $value = $dimension->values()->create([
            ...$request->validated(),
            'sort_order' => ((int) $dimension->values()->max('sort_order')) + 1,
        ]);

        return response()->json(
            DimensionValueResource::make($value->refresh())->resolve($request),
            201
        );
    }

    public function update(
        UpdateDimensionValueRequest $request,
        Game $game,
        GameContentType $gameContentType,
        Dimension $dimension,
        DimensionValue $dimensionValue
    ): JsonResponse {
        $this->ensureValueBelongsToContext(
            $game,
            $gameContentType,
            $dimension,
            $dimensionValue
        );
        $dimensionValue->update($request->validated());

        return response()->json(
            DimensionValueResource::make($dimensionValue->refresh())->resolve($request)
        );
    }

    public function destroy(
        Game $game,
        GameContentType $gameContentType,
        Dimension $dimension,
        DimensionValue $dimensionValue
    ): JsonResponse {
        $this->ensureValueBelongsToContext(
            $game,
            $gameContentType,
            $dimension,
            $dimensionValue
        );
        $dimensionValue->delete();

        return response()->json(['message' => 'Значение фильтра удалено.']);
    }

    private function ensureDimensionBelongsToContext(
        Game $game,
        GameContentType $gameContentType,
        Dimension $dimension
    ): void {
        abort_unless($gameContentType->game_id === $game->id, 404);
        abort_unless($dimension->game_content_type_id === $gameContentType->id, 404);
    }

    private function ensureValueBelongsToContext(
        Game $game,
        GameContentType $gameContentType,
        Dimension $dimension,
        DimensionValue $dimensionValue
    ): void {
        $this->ensureDimensionBelongsToContext($game, $gameContentType, $dimension);
        abort_unless($dimensionValue->dimension_id === $dimension->id, 404);
    }
}
