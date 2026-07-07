<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum CommonStatus: string
{
    use EnumOptions;

    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case BLOCKED = 'blocked';

    public function labels(): string
    {
        return __("common.status.{$this->value}");
    }
}
