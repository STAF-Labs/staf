<?php

namespace App\Http\Requests\Admin\ContentType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', Rule::unique('content_types', 'name')],
            'is_public' => ['required', 'boolean'],
        ];
    }
}
