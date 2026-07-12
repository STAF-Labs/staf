<?php

namespace App\Enums;

use App\Concerns\EnumOptions;
use Illuminate\Contracts\Support\Htmlable;

enum MembershipStatus: string
{
    use EnumOptions;

    case ACTIVE = 'active';
    case INVITED = 'invited';
    case SUSPENDED = 'suspended';

    public function getLabel(): string|Htmlable|null
    {
        return __("enum.membership.status.{$this->value}");
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INVITED => 'warning',
            self::SUSPENDED => 'gray',
        };
    }
}
