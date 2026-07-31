<?php

namespace App\Http\Requests\Admin\ContentType;

use App\Models\Game\ContentType\ContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentTypeRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:64',
                Rule::unique('content_types', 'name')->ignore($this->contentType()),
            ],
            'is_public' => ['required', 'boolean'],
        ];
    }

    private function contentType(): ?ContentType
    {
        $contentType = $this->route('contentType');

        return $contentType instanceof ContentType ? $contentType : null;
    }
}
