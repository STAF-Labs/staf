<?php

namespace App\Http\Requests\Admin\Project;

use App\Models\Game\Filter\Dimension;
use App\Models\Game\Filter\DimensionValue;
use App\Models\Org\Organization;
use App\Models\User\User;
use App\Rules\FilledTipTapDocument;
use App\Services\Admin\Licence\SpdxLicenceCatalog;
use App\Services\Admin\Project\ProjectUploadLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectRequest extends FormRequest
{
    public const LOGO_MAX_KILOBYTES = 2048;

    public const LOGO_MAX_WIDTH = 1024;

    public const LOGO_MAX_HEIGHT = 1024;

    public const SCREENSHOT_MAX_KILOBYTES = 5120;

    public const SCREENSHOT_MAX_WIDTH = 3840;

    public const SCREENSHOT_MAX_HEIGHT = 2160;

    public const SCREENSHOTS_MAX_COUNT = 20;

    /**
     * @return list<string>
     */
    public static function imageMimeTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp'];
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(SpdxLicenceCatalog $licenceCatalog): array
    {
        return [
            'ownerable_type' => ['required', 'string', Rule::in([User::class, Organization::class])],
            'ownerable_id' => ['required', 'integer'],
            'game_content_type_id' => ['required', 'integer', Rule::exists('game_content_types', 'id')],
            'title' => ['required', 'string', 'max:128'],
            'slug' => ['nullable', 'string', 'max:128', 'unique:projects,slug'],
            'summary' => ['nullable', 'array', new FilledTipTapDocument],
            'description' => ['nullable', 'array', new FilledTipTapDocument],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'min:3'],
            'website_urls' => ['nullable', 'array'],
            'website_urls.*' => ['url', 'starts_with:https://'],
            'licence_name' => ['nullable', 'string', 'max:128', Rule::in($licenceCatalog->identifiers())],
            'percentage_complete' => ['sometimes', 'integer', 'between:0,100'],
            'dimension_value_ids' => ['nullable', 'array'],
            'dimension_value_ids.*' => ['integer', 'distinct', Rule::exists('dimension_values', 'id')],
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:'.implode(',', self::imageMimeTypes()),
                'max:'.self::LOGO_MAX_KILOBYTES,
                Rule::dimensions()
                    ->maxWidth(self::LOGO_MAX_WIDTH)
                    ->maxHeight(self::LOGO_MAX_HEIGHT),
            ],
            'screenshots' => ['nullable', 'array', 'max:'.self::SCREENSHOTS_MAX_COUNT],
            'screenshots.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:'.implode(',', self::imageMimeTypes()),
                'max:'.self::SCREENSHOT_MAX_KILOBYTES,
                Rule::dimensions()
                    ->maxWidth(self::SCREENSHOT_MAX_WIDTH)
                    ->maxHeight(self::SCREENSHOT_MAX_HEIGHT),
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
                $ownerableType = $this->string('ownerable_type')->toString();
                $ownerableId = $this->integer('ownerable_id');

                if (! in_array($ownerableType, [User::class, Organization::class], true)) {
                    return;
                }

                if (! $ownerableType::query()->whereKey($ownerableId)->exists()) {
                    $validator->errors()->add('ownerable_id', 'Владелец не найден.');
                }
            },
            function (Validator $validator): void {
                $this->validateProjectDimensionValues($validator);
            },
            function (Validator $validator): void {
                $this->validateUploadTotalSize($validator);
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
        $gameContentTypeId = $this->integer('game_content_type_id');

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

        $parentValueNames = DimensionValue::query()
            ->whereIn('id', $selectedValueIds)
            ->whereHas('children', fn ($query) => $query->where('is_active', true))
            ->pluck('name');

        if ($parentValueNames->isNotEmpty()) {
            $validator->errors()->add(
                'dimension_value_ids',
                'Выбирайте только конечные значения настроек проекта: '.$parentValueNames->join(', ').'.'
            );
        }
    }

    private function validateUploadTotalSize(Validator $validator): void
    {
        $limits = app(ProjectUploadLimits::class);

        if ($limits->exceedsLimit($this, ['logo', 'screenshots'])) {
            $validator->errors()->add('files', $limits->errorMessage());
        }
    }
}
