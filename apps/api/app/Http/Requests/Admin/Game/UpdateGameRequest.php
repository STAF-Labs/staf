<?php

namespace App\Http\Requests\Admin\Game;

use App\Enums\CommonStatus;
use App\Rules\FilledTipTapDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'json', new FilledTipTapDocument],
            'released_at' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['required', Rule::enum(CommonStatus::class)],
            'logo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:'.implode(',', StoreGameRequest::imageMimeTypes()),
                'max:'.StoreGameRequest::LOGO_MAX_KILOBYTES,
                Rule::dimensions()
                    ->maxWidth(StoreGameRequest::LOGO_MAX_WIDTH)
                    ->maxHeight(StoreGameRequest::LOGO_MAX_HEIGHT),
            ],
            'banner' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:'.implode(',', StoreGameRequest::imageMimeTypes()),
                'max:'.StoreGameRequest::BANNER_MAX_KILOBYTES,
                Rule::dimensions()
                    ->maxWidth(StoreGameRequest::BANNER_MAX_WIDTH)
                    ->maxHeight(StoreGameRequest::BANNER_MAX_HEIGHT),
            ],
        ];
    }
}
