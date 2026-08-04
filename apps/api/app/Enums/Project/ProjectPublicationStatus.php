<?php

namespace App\Enums\Project;

use App\Concerns\EnumOptions;
use Illuminate\Contracts\Support\Htmlable;

enum ProjectPublicationStatus: string
{
    use EnumOptions;

    case PUBLIC = 'public';
    case PRIVATE = 'private';
    case URL_ONLY = 'url_only';
    case ARCHIVED = 'archived';

    public function getLabel(): string|Htmlable|null
    {
        return __("enum.project.publication_status.{$this->value}");
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PUBLIC => 'success',
            self::PRIVATE => 'gray',
            self::URL_ONLY => 'warning',
            self::ARCHIVED => 'info',
        };
    }
}
