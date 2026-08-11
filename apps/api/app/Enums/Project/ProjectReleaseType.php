<?php

namespace App\Enums\Project;

use App\Concerns\EnumOptions;
use Illuminate\Contracts\Support\Htmlable;

enum ProjectReleaseType: string
{
    use EnumOptions;

    case ALPHA = 'alpha';
    case BETA = 'beta';
    case RELEASE = 'release';

    public function getLabel(): string|Htmlable|null
    {
        return __("enum.project.release.type.{$this->value}");
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ALPHA => 'warning',
            self::BETA => 'info',
            self::RELEASE => 'success',
        };
    }
}
