<?php

namespace App\Enums\Project;

use App\Concerns\EnumOptions;
use Illuminate\Contracts\Support\Htmlable;

enum ProjectReleaseStatus: string
{
    use EnumOptions;

    case PUBLISHED = 'published';
    case ON_MODERATION = 'on_moderation';
    case ARCHIVED = 'archived';

    public function getLabel(): string|Htmlable|null
    {
        return __("enum.project.release.status.{$this->value}");
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PUBLISHED => 'success',
            self::ON_MODERATION => 'warning',
            self::ARCHIVED => 'info',
        };
    }
}
