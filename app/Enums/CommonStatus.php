<?php

namespace App\Enums;

use App\Concerns\EnumOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum CommonStatus: string implements HasColor, HasLabel
{
    use EnumOptions;

    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case BLOCKED = 'blocked';

    public function getLabel(): string|Htmlable|null
    {
        return __("common.status.{$this->value}");
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
