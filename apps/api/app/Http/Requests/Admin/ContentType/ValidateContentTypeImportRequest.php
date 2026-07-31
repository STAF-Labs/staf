<?php

namespace App\Http\Requests\Admin\ContentType;

use Illuminate\Foundation\Http\FormRequest;

class ValidateContentTypeImportRequest extends FormRequest
{
    public const MAX_FILE_KILOBYTES = 4096;

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
            'file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx',
                'max:'.self::MAX_FILE_KILOBYTES,
            ],
        ];
    }
}
