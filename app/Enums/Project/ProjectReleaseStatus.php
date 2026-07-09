<?php

namespace App\Enums\Project;

use App\Concerns\EnumOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ProjectReleaseStatus: string implements HasColor, HasLabel
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
