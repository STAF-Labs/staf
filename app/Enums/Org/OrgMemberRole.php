<?php

namespace App\Enums\Org;

use App\Concerns\EnumOptions;
use Illuminate\Contracts\Support\Htmlable;

enum OrgMemberRole: string
{
    use EnumOptions;

    case OWNER = 'owner';
    case MAINTAINER = 'maintainer';
    case MEMBER = 'member';
    case READER = 'reader';

    public function getLabel(): string|Htmlable|null
    {
        return __("enum.org.member.role.{$this->value}");
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OWNER => 'success',
            self::MAINTAINER => 'info',
            self::MEMBER => 'warning',
            self::READER => 'gray',
        };
    }
}
