<?php

namespace App\Enums\Project;

use App\Concerns\EnumOptions;
use Illuminate\Contracts\Support\Htmlable;

enum ProjectMemberRole: string
{
    use EnumOptions;

    case OWNER = 'owner';
    case MAINTAINER = 'maintainer';
    case MEMBER = 'member';

    public function getLabel(): string|Htmlable|null
    {
        return __("enum.project.member.role.{$this->value}");
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OWNER => 'success',
            self::MAINTAINER => 'info',
            self::MEMBER => 'warning',
        };
    }
}
