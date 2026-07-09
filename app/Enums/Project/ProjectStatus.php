<?php

namespace App\Enums\Project;

use App\Concerns\EnumOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ProjectStatus: string implements HasColor, HasLabel
{
    use EnumOptions;

    case DRAFT = 'draft';
    case ON_MODERATION = 'on_moderation';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';
    case ARCHIVED = 'archived';

    public function getLabel(): string|Htmlable|null
    {
        return __("enum.project.status.{$this->value}");
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ON_MODERATION => 'warning',
            self::PUBLISHED => 'success',
            self::REJECTED => 'danger',
            self::ARCHIVED => 'info',
        };
    }
}
