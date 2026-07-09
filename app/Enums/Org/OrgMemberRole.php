<?php

namespace App\Enums\Org;

use App\Concerns\EnumOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum OrgMemberRole: string implements HasColor, HasLabel
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
