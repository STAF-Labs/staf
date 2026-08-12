<?php

namespace App\Http\Requests\Admin\Project;

use App\Enums\Project\ProjectPublicationStatus;
use App\Enums\Project\ProjectStatus;
use App\Models\Game\Filter\Dimension;
use App\Models\Game\Project\Project;
use App\Rules\FilledTipTapDocument;
use App\Services\Admin\Licence\SpdxLicenceCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
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
    public function rules(SpdxLicenceCatalog $licenceCatalog): array
    {
        return [
            'ownerable_type' => ['prohibited'],
            'ownerable_id' => ['prohibited'],
            'game_content_type_id' => ['sometimes', 'integer', Rule::exists('game_content_types', 'id')],
            'title' => ['sometimes', 'string', 'max:128'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:128', Rule::unique('projects', 'slug')->ignore($this->route('project'))],
            'summary' => ['sometimes', 'nullable', 'array', new FilledTipTapDocument],
            'description' => ['sometimes', 'nullable', 'array', new FilledTipTapDocument],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'min:3'],
            'website_urls' => ['sometimes', 'nullable', 'array'],
            'website_urls.*' => ['url', 'starts_with:https://'],
            'licence_name' => ['sometimes', 'nullable', 'string', 'max:128', Rule::in($licenceCatalog->identifiers())],
            'percentage_complete' => ['sometimes', 'integer', 'between:0,100'],
            'publication_status' => ['sometimes', Rule::enum(ProjectPublicationStatus::class)],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'dimension_value_ids' => ['sometimes', 'nullable', 'array'],
            'dimension_value_ids.*' => ['integer', 'distinct', Rule::exists('dimension_values', 'id')],
            'logo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:'.implode(',', StoreProjectRequest::imageMimeTypes()),
                'max:'.StoreProjectRequest::LOGO_MAX_KILOBYTES,
                Rule::dimensions()
                    ->maxWidth(StoreProjectRequest::LOGO_MAX_WIDTH)
                    ->maxHeight(StoreProjectRequest::LOGO_MAX_HEIGHT),
            ],
            'screenshots' => ['nullable', 'array', 'max:'.StoreProjectRequest::SCREENSHOTS_MAX_COUNT],
            'screenshots.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:'.implode(',', StoreProjectRequest::imageMimeTypes()),
                'max:'.StoreProjectRequest::SCREENSHOT_MAX_KILOBYTES,
                Rule::dimensions()
                    ->maxWidth(StoreProjectRequest::SCREENSHOT_MAX_WIDTH)
                    ->maxHeight(StoreProjectRequest::SCREENSHOT_MAX_HEIGHT),
            ],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateProjectDimensionValues($validator);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $decoded = [];

        foreach (['summary', 'description', 'tags', 'website_urls', 'dimension_value_ids'] as $field) {
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

    private function validateProjectDimensionValues(Validator $validator): void
    {
        if (! $this->exists('dimension_value_ids')) {
            return;
        }

        $project = $this->route('project');
        $gameContentTypeId = $this->integer('game_content_type_id')
            ?: ($project instanceof Project ? $project->game_content_type_id : 0);

        if ($gameContentTypeId <= 0) {
            return;
        }

        $selectedValueIds = collect($this->input('dimension_value_ids', []))
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values();
        $dimensions = Dimension::query()
            ->where('game_content_type_id', $gameContentTypeId)
            ->where('applies_to', 'project')
            ->where('is_active', true)
            ->with(['values' => fn ($query) => $query->where('is_active', true)])
            ->get();
        $allowedValueIds = $dimensions->flatMap(
            fn (Dimension $dimension) => $dimension->values->pluck('id')
        );

        if ($selectedValueIds->diff($allowedValueIds)->isNotEmpty()) {
            $validator->errors()->add(
                'dimension_value_ids',
                'Выбраны недоступные значения настроек проекта.'
            );
        }

        foreach ($dimensions as $dimension) {
            $dimensionSelectedIds = $selectedValueIds->intersect($dimension->values->pluck('id'));

            if ($dimension->is_required && $dimensionSelectedIds->isEmpty()) {
                $validator->errors()->add(
                    'dimension_value_ids',
                    "Не заполнена обязательная настройка: {$dimension->name}."
                );
            }

            if ($dimension->selection_mode->value === 'single' && $dimensionSelectedIds->count() > 1) {
                $validator->errors()->add(
                    'dimension_value_ids',
                    "Для настройки {$dimension->name} можно выбрать только одно значение."
                );
            }
        }
    }
}
