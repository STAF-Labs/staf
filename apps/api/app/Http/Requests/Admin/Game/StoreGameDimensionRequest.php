<?php

namespace App\Http\Requests\Admin\Game;

use App\Enums\Filter\DimSelectionMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameDimensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'selection_mode' => ['required', Rule::enum(DimSelectionMode::class)],
            'is_filterable' => ['required', 'boolean'],
        ];
    }
}
