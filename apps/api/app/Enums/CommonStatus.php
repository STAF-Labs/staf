<?php

namespace App\Enums;

use App\Concerns\EnumOptions;
use Illuminate\Contracts\Support\Htmlable;

enum CommonStatus: string
{
    use EnumOptions;

    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case BLOCKED = 'blocked';

    public function getLabel(): string|Htmlable|null
    {
        return __("enum.common.status.{$this->value}");
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::SUSPENDED => 'gray',
            self::BLOCKED => 'danger',
        };
    }
}
