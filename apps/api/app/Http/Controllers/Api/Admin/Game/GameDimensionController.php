<?php

namespace App\Http\Controllers\Api\Admin\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Game\CopyGameDimensionsRequest;
use App\Http\Requests\Admin\Game\StoreGameDimensionRequest;
use App\Http\Requests\Admin\Game\UpdateGameDimensionRequest;
use App\Http\Requests\Admin\Game\ValidateGameDimensionImportRequest;
use App\Http\Resources\Game\Filter\DimensionResource;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Filter\Dimension;
use App\Models\Game\Game;
use App\Services\Admin\Game\GameDimensionImportParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GameDimensionController extends Controller
{
    public function index(Request $request, Game $game, GameContentType $gameContentType): JsonResponse
    {
        $this->ensureGameContentTypeBelongsToGame($game, $gameContentType);

        $dimensions = $gameContentType->dimensions()
            ->with(['values' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => DimensionResource::collection($dimensions)->resolve($request),
            'total' => $dimensions->count(),
            'filtered_total' => $dimensions->count(),
        ]);
    }

    public function store(
        StoreGameDimensionRequest $request,
        Game $game,
        GameContentType $gameContentType
    ): JsonResponse {
        $this->ensureGameContentTypeBelongsToGame($game, $gameContentType);

        $dimension = $gameContentType->dimensions()->create([
            ...$request->validated(),
            'sort_order' => ((int) $gameContentType->dimensions()->max('sort_order')) + 1,
        ]);

        return response()->json(
            DimensionResource::make($dimension->refresh()->load('values'))->resolve($request),
            201
        );
    }

    public function copy(
        CopyGameDimensionsRequest $request,
        Game $game,
        GameContentType $gameContentType
    ): JsonResponse {
        $this->ensureGameContentTypeBelongsToGame($game, $gameContentType);

        $sourceGameContentType = $game->gameContentTypes()
            ->findOrFail($request->integer('source_game_content_type_id'));
        $dimensionIds = $request->validated('dimension_ids');
        $sourceDimensions = $sourceGameContentType->dimensions()
            ->whereKey($dimensionIds)
            ->with(['values' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $summary = DB::transaction(function () use ($gameContentType, $sourceDimensions): array {
            $createdFilters = 0;
            $reusedFilters = 0;
            $createdValues = 0;
            $skippedValues = 0;
            $nextDimensionSortOrder = ((int) $gameContentType->dimensions()->max('sort_order')) + 1;

            foreach ($sourceDimensions as $sourceDimension) {
                $targetDimension = $gameContentType->dimensions()
                    ->where('slug', $sourceDimension->slug)
                    ->first();

                if ($targetDimension === null) {
                    $targetDimension = $gameContentType->dimensions()
                        ->get()
                        ->first(
                            fn ($dimension): bool => $this->normalizedName($dimension->name) ===
                                $this->normalizedName($sourceDimension->name)
                        );
                }

                if ($targetDimension === null) {
                    $targetDimension = $gameContentType->dimensions()->create([
                        'name' => $sourceDimension->name,
                        'slug' => $sourceDimension->slug,
                        'selection_mode' => $sourceDimension->selection_mode->value,
                        'applies_to' => $sourceDimension->applies_to->value,
                        'is_filterable' => $sourceDimension->is_filterable,
                        'is_required' => $sourceDimension->is_required,
                        'is_active' => $sourceDimension->is_active,
                        'sort_order' => $nextDimensionSortOrder,
                        'notes' => $sourceDimension->notes,
                    ]);
                    $nextDimensionSortOrder++;
                    $createdFilters++;
                } else {
                    $reusedFilters++;
                }

                $targetValues = $targetDimension->values()->get();
                $targetValuesByName = $targetValues->keyBy(
                    fn ($value): string => $this->normalizedName($value->name)
                );
                $sourceToTargetValueIds = [];
                $createdSourceValueIds = [];
                $nextValueSortOrder = ((int) $targetValues->max('sort_order')) + 1;

                foreach ($sourceDimension->values as $sourceValue) {
                    $normalizedName = $this->normalizedName($sourceValue->name);
                    $targetValue = $targetValuesByName->get($normalizedName);

                    if ($targetValue !== null) {
                        $sourceToTargetValueIds[$sourceValue->id] = $targetValue->id;
                        $skippedValues++;

                        continue;
                    }

                    $targetValue = $targetDimension->values()->create([
                        'name' => $sourceValue->name,
                        'sort_order' => $nextValueSortOrder,
                        'is_active' => $sourceValue->is_active,
                    ]);
                    $nextValueSortOrder++;
                    $createdValues++;
                    $createdSourceValueIds[] = $sourceValue->id;
                    $sourceToTargetValueIds[$sourceValue->id] = $targetValue->id;
                    $targetValuesByName->put($normalizedName, $targetValue);
                }

                foreach ($sourceDimension->values as $sourceValue) {
                    if (
                        $sourceValue->parent_id === null ||
                        ! in_array($sourceValue->id, $createdSourceValueIds, true)
                    ) {
                        continue;
                    }

                    $parentId = $sourceToTargetValueIds[$sourceValue->parent_id] ?? null;

                    if ($parentId !== null) {
                        $targetDimension->values()
                            ->whereKey($sourceToTargetValueIds[$sourceValue->id])
                            ->update(['parent_id' => $parentId]);
                    }
                }
            }

            return [
                'created_filters' => $createdFilters,
                'reused_filters' => $reusedFilters,
                'created_values' => $createdValues,
                'skipped_values' => $skippedValues,
            ];
        });

        $dimensions = $gameContentType->dimensions()
            ->with(['values' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            ...$summary,
            'data' => DimensionResource::collection($dimensions)->resolve($request),
        ]);
    }

    public function validateImport(
        ValidateGameDimensionImportRequest $request,
        Game $game,
        GameContentType $gameContentType,
        GameDimensionImportParser $parser
    ): JsonResponse {
        $this->ensureGameContentTypeBelongsToGame($game, $gameContentType);
        $result = $parser->parse($request->file('file'));

        if (! $result['valid']) {
            throw ValidationException::withMessages([
                'file' => [$result['message']],
            ]);
        }

        return response()->json($result);
    }

    public function import(
        ValidateGameDimensionImportRequest $request,
        Game $game,
        GameContentType $gameContentType,
        GameDimensionImportParser $parser
    ): JsonResponse {
        $this->ensureGameContentTypeBelongsToGame($game, $gameContentType);
        $result = $parser->parse($request->file('file'));

        if (! $result['valid']) {
            throw ValidationException::withMessages([
                'file' => [$result['message']],
            ]);
        }

        $summary = DB::transaction(function () use ($gameContentType, $result): array {
            $createdFilters = 0;
            $reusedFilters = 0;
            $createdValues = 0;
            $skippedValues = 0;
            $dimensionsByKey = [];
            $valuesByKey = [];
            $createdValueKeys = [];

            foreach ($result['filters'] as $filterRow) {
                $dimension = $gameContentType->dimensions()
                    ->get()
                    ->first(
                        fn ($item): bool => $this->normalizedName($item->name) ===
                            $this->normalizedName($filterRow['name'])
                    );

                if ($dimension === null) {
                    $dimension = $gameContentType->dimensions()->create([
                        'name' => $filterRow['name'],
                        'selection_mode' => $filterRow['selection_mode'],
                        'applies_to' => $filterRow['applies_to'],
                        'is_filterable' => $filterRow['is_filterable'],
                        'is_required' => $filterRow['is_required'],
                        'is_active' => $filterRow['is_active'],
                        'sort_order' => $filterRow['row'] - 2,
                    ]);
                    $createdFilters++;
                } else {
                    $reusedFilters++;
                }

                $dimensionsByKey[$filterRow['filter_key']] = $dimension;
            }

            foreach ($result['values'] as $valueRow) {
                $dimension = $dimensionsByKey[$valueRow['filter_key']];
                $value = $dimension->values()
                    ->get()
                    ->first(
                        fn ($item): bool => $this->normalizedName($item->name) ===
                            $this->normalizedName($valueRow['name'])
                    );
                $compositeKey = $valueRow['filter_key'].':'.$valueRow['value_key'];

                if ($value === null) {
                    $value = $dimension->values()->create([
                        'name' => $valueRow['name'],
                        'sort_order' => $valueRow['sort_order'],
                        'is_active' => $valueRow['is_active'],
                    ]);
                    $createdValueKeys[$compositeKey] = true;
                    $createdValues++;
                } else {
                    $skippedValues++;
                }

                $valuesByKey[$compositeKey] = $value;
            }

            foreach ($result['values'] as $valueRow) {
                $compositeKey = $valueRow['filter_key'].':'.$valueRow['value_key'];

                if ($valueRow['parent_key'] === null || ! isset($createdValueKeys[$compositeKey])) {
                    continue;
                }

                $parentKey = $valueRow['filter_key'].':'.$valueRow['parent_key'];
                $valuesByKey[$compositeKey]->update([
                    'parent_id' => $valuesByKey[$parentKey]->id,
                ]);
            }

            return [
                'created_filters' => $createdFilters,
                'reused_filters' => $reusedFilters,
                'created_values' => $createdValues,
                'skipped_values' => $skippedValues,
            ];
        });

        return response()->json([
            'message' => 'Импорт фильтров завершён.',
            'total_filters' => count($result['filters']),
            'total_values' => count($result['values']),
            ...$summary,
        ]);
    }

    public function update(
        UpdateGameDimensionRequest $request,
        Game $game,
        GameContentType $gameContentType,
        Dimension $dimension
    ): JsonResponse {
        $this->ensureDimensionBelongsToContext($game, $gameContentType, $dimension);
        $validated = $request->validated();

        if (
            isset($validated['applies_to']) &&
            $validated['applies_to'] !== $dimension->applies_to->value &&
            ($dimension->projectSelections()->exists() || $dimension->releaseSelections()->exists())
        ) {
            return response()->json([
                'message' => 'Нельзя изменить уровень фильтра, пока его значения используются.',
            ], 409);
        }

        $dimension->update($validated);

        return response()->json(
            DimensionResource::make(
                $dimension->refresh()->load(['values' => fn ($query) => $query
                    ->orderBy('sort_order')
                    ->orderBy('id')])
            )->resolve($request)
        );
    }

    public function destroy(
        Game $game,
        GameContentType $gameContentType,
        Dimension $dimension
    ): JsonResponse {
        $this->ensureDimensionBelongsToContext($game, $gameContentType, $dimension);
        $dimension->delete();

        return response()->json(['message' => 'Фильтр удалён.']);
    }

    private function ensureGameContentTypeBelongsToGame(
        Game $game,
        GameContentType $gameContentType
    ): void {
        abort_unless($gameContentType->game_id === $game->id, 404);
    }

    private function ensureDimensionBelongsToContext(
        Game $game,
        GameContentType $gameContentType,
        Dimension $dimension
    ): void {
        $this->ensureGameContentTypeBelongsToGame($game, $gameContentType);
        abort_unless($dimension->game_content_type_id === $gameContentType->id, 404);
    }

    private function normalizedName(string $name): string
    {
        return mb_strtolower(trim($name), 'UTF-8');
    }
}
