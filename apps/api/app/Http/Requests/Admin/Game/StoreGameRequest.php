<?php

namespace App\Http\Requests\Admin\Game;

use App\Enums\CommonStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameRequest extends FormRequest
{
    public const BANNER_MAX_KILOBYTES = 4096;

    public const BANNER_MAX_WIDTH = 2560;

    public const BANNER_MAX_HEIGHT = 960;

    public const LOGO_MAX_KILOBYTES = 2048;

    public const LOGO_MAX_WIDTH = 1024;

    public const LOGO_MAX_HEIGHT = 1024;

    /**
     * @return list<string>
     */
    public static function imageMimeTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp'];
    }

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
            'description' => ['nullable', 'json'],
            'released_at' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['required', Rule::enum(CommonStatus::class)],
            'logo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:'.implode(',', self::imageMimeTypes()),
                'max:'.self::LOGO_MAX_KILOBYTES,
                Rule::dimensions()
                    ->maxWidth(self::LOGO_MAX_WIDTH)
                    ->maxHeight(self::LOGO_MAX_HEIGHT),
            ],
            'banner' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:'.implode(',', self::imageMimeTypes()),
                'max:'.self::BANNER_MAX_KILOBYTES,
                Rule::dimensions()
                    ->maxWidth(self::BANNER_MAX_WIDTH)
                    ->maxHeight(self::BANNER_MAX_HEIGHT),
            ],
        ];
    }
}
