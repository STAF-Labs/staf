<?php

namespace App\Http\Requests\Admin\Game;

use App\Models\Game\Filter\Dimension;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDimensionValueRequest extends FormRequest
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
                'required',
                'string',
                'max:64',
                Rule::unique('dimension_values', 'name')
                    ->where('dimension_id', $this->dimension()?->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function dimension(): ?Dimension
    {
        $dimension = $this->route('dimension');

        return $dimension instanceof Dimension ? $dimension : null;
    }
}
