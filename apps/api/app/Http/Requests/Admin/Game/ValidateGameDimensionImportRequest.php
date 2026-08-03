<?php

namespace App\Http\Requests\Admin\Game;

use Illuminate\Foundation\Http\FormRequest;

class ValidateGameDimensionImportRequest extends FormRequest
{
    public const MAX_FILE_KILOBYTES = 10240;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx',
                'max:'.self::MAX_FILE_KILOBYTES,
            ],
        ];
    }
}
