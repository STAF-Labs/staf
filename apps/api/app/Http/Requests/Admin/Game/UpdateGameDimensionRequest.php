<?php

namespace App\Http\Requests\Admin\Game;

use App\Enums\Filter\DimensionAppliesTo;
use App\Enums\Filter\DimSelectionMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameDimensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:64'],
            'selection_mode' => ['sometimes', 'required', Rule::enum(DimSelectionMode::class)],
            'applies_to' => ['sometimes', 'required', Rule::enum(DimensionAppliesTo::class)],
            'is_filterable' => ['sometimes', 'required', 'boolean'],
            'is_required' => ['sometimes', 'required', 'boolean'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}
