<?php

namespace App\Services\Admin\Project;

use App\Models\Game\Filter\Dimension;
use App\Models\Game\Filter\DimensionValue;
use App\Models\Game\Project\Project;
use App\Services\RichText\TipTapDocumentContent;

class ProjectPublicationReadiness
{
    public function __construct(
        private readonly TipTapDocumentContent $content,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, list<string>>
     */
    public function errors(Project $project, array $validated, bool $hasNewLogo = false): array
    {
        $errors = [];
        $gameContentTypeId = (int) ($validated['game_content_type_id'] ?? $project->game_content_type_id);
        $dimensionValueIds = $this->dimensionValueIds($project, $validated);

        if (blank($project->ownerable_type) || blank($project->ownerable_id)) {
            $errors['ownerable_id'][] = 'У проекта должен быть автор.';
        }

        if ($gameContentTypeId <= 0) {
            $errors['game_content_type_id'][] = 'Выберите тип контента проекта.';
        }

        if (blank($validated['title'] ?? $project->title)) {
            $errors['title'][] = 'Введите название проекта.';
        }

        if (! $hasNewLogo && ! $project->hasMedia('logo')) {
            $errors['logo'][] = 'Добавьте логотип проекта.';
        }

        if (! $this->content->hasText($validated['summary'] ?? $project->summary)) {
            $errors['summary'][] = 'Добавьте краткое описание проекта.';
        }

        if (! $this->content->hasText($validated['description'] ?? $project->description)) {
            $errors['description'][] = 'Добавьте описание проекта.';
        }

        if ($gameContentTypeId > 0) {
            $errors = array_merge_recursive(
                $errors,
                $this->dimensionErrors($gameContentTypeId, $dimensionValueIds)
            );
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<int>
     */
    private function dimensionValueIds(Project $project, array $validated): array
    {
        if (array_key_exists('dimension_value_ids', $validated)) {
            return collect($validated['dimension_value_ids'] ?? [])
                ->map(fn (mixed $value): int => (int) $value)
                ->unique()
                ->values()
                ->all();
        }

        return $project->dimensionValues()
            ->pluck('dimension_values.id')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();
    }

    /**
     * @param  list<int>  $selectedValueIds
     * @return array<string, list<string>>
     */
    private function dimensionErrors(int $gameContentTypeId, array $selectedValueIds): array
    {
        $errors = [];
        $selectedValueIds = collect($selectedValueIds);
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
            $errors['dimension_value_ids'][] = 'Выбраны недоступные значения настроек проекта.';
        }

        foreach ($dimensions as $dimension) {
            $dimensionSelectedIds = $selectedValueIds->intersect($dimension->values->pluck('id'));

            if (! $dimension->is_required) {
                if ($dimension->selection_mode->value === 'single' && $dimensionSelectedIds->count() > 1) {
                    $errors['dimension_value_ids'][] = "Для настройки {$dimension->name} можно выбрать только одно значение.";
                }

                continue;
            }

            if ($dimensionSelectedIds->isEmpty()) {
                $errors['dimension_value_ids'][] = "Не заполнена обязательная настройка: {$dimension->name}.";
            }

            if ($dimension->selection_mode->value === 'single' && $dimensionSelectedIds->count() > 1) {
                $errors['dimension_value_ids'][] = "Для настройки {$dimension->name} можно выбрать только одно значение.";
            }
        }

        $parentValueNames = DimensionValue::query()
            ->whereIn('id', $selectedValueIds)
            ->whereHas('children', fn ($query) => $query->where('is_active', true))
            ->pluck('name');

        if ($parentValueNames->isNotEmpty()) {
            $errors['dimension_value_ids'][] = 'Выбирайте только конечные значения настроек проекта: '.$parentValueNames->join(', ').'.';
        }

        return $errors;
    }
}
