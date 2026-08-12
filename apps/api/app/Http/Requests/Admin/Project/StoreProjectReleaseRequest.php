<?php

namespace App\Http\Requests\Admin\Project;

use App\Enums\Project\ProjectReleaseType;
use App\Models\Game\Filter\Dimension;
use App\Models\Game\Project\Project;
use App\Rules\FilledTipTapDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $project = $this->route('project');

        if ($user === null || ! $project instanceof Project) {
            return false;
        }

        return $user->can('update', $project);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
            ],
            'title' => ['required', 'string', 'max:64'],
            'type' => ['required', Rule::enum(ProjectReleaseType::class)],
            'changelog' => ['required', 'array', new FilledTipTapDocument],
            'dimension_value_ids' => ['nullable', 'array'],
            'dimension_value_ids.*' => ['integer', 'distinct', Rule::exists('dimension_values', 'id')],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateReleaseDimensionValues($validator);
            },
        ];
    }

    protected function prepareForValidation(): void
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

    private function validateReleaseDimensionValues(Validator $validator): void
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
