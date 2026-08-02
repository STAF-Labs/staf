<?php

namespace App\Http\Requests\Admin\Game;

use App\Models\Game\Filter\Dimension;
use App\Models\Game\Filter\DimensionValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDimensionValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                Rule::unique('dimension_values', 'name')
                    ->where('dimension_id', $this->dimension()?->id)
                    ->ignore($this->dimensionValue()),
            ],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }

    private function dimension(): ?Dimension
    {
        $dimension = $this->route('dimension');

        return $dimension instanceof Dimension ? $dimension : null;
    }

    private function dimensionValue(): ?DimensionValue
    {
        $dimensionValue = $this->route('dimensionValue');

        return $dimensionValue instanceof DimensionValue ? $dimensionValue : null;
    }
}
