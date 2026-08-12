<?php

namespace App\Http\Requests\Admin\Project\Concerns;

use App\Models\Game\Filter\Dimension;
use App\Models\Game\Project\Project;
use Illuminate\Validation\Validator;

trait ValidatesProjectReleaseDimensions
{
    protected function prepareReleasePayloadForValidation(): void
    {
        $decoded = [];

        foreach (['changelog', 'dimension_value_ids'] as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $json = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded[$field] = $json;
            }
        }

        $this->merge($decoded);
    }

    protected function validateReleaseDimensionValues(Validator $validator): void
    {
        $project = $this->route('project');

        if (! $project instanceof Project || $project->game_content_type_id === null) {
            return;
        }

        $selectedValueIds = collect($this->input('dimension_value_ids', []))
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values();
        $dimensions = Dimension::query()
            ->where('game_content_type_id', $project->game_content_type_id)
            ->where('applies_to', 'release')
            ->where('is_active', true)
            ->with(['values' => fn ($query) => $query->where('is_active', true)])
            ->get();
        $allowedValueIds = $dimensions->flatMap(
            fn (Dimension $dimension) => $dimension->values->pluck('id')
        );

        if ($selectedValueIds->diff($allowedValueIds)->isNotEmpty()) {
            $validator->errors()->add(
                'dimension_value_ids',
                'Выбраны недоступные значения фильтров релиза.'
            );
        }

        foreach ($dimensions as $dimension) {
            $dimensionSelectedIds = $selectedValueIds->intersect($dimension->values->pluck('id'));

            if ($dimension->is_required && $dimensionSelectedIds->isEmpty()) {
                $validator->errors()->add(
                    'dimension_value_ids',
                    "Не заполнен обязательный фильтр релиза: {$dimension->name}."
                );
            }

            if ($dimension->selection_mode->value === 'single' && $dimensionSelectedIds->count() > 1) {
                $validator->errors()->add(
                    'dimension_value_ids',
                    "Для фильтра {$dimension->name} можно выбрать только одно значение."
                );
            }
        }
    }
}
