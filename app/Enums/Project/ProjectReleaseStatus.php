<?php

namespace App\Enums\Project;

use App\Concerns\EnumOptions;
use Illuminate\Contracts\Support\Htmlable;

enum ProjectReleaseStatus: string
{
    use EnumOptions;

    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function getLabel(): string|Htmlable|null
    {
        return __("enum.project.release.status.{$this->value}");
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PUBLISHED => 'success',
            self::ARCHIVED => 'info',
        };
    }
}
