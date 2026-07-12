<?php

namespace App\Enums\Filter;

use App\Concerns\EnumOptions;
use Illuminate\Contracts\Support\Htmlable;

enum DimSelectionMode: string
{
    use EnumOptions;

    case SINGLE = 'single';
    case MULTIPLE = 'multiple';

    public function getLabel(): string|Htmlable|null
    {
        return __("enum.dim.selection_mode.{$this->value}");
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SINGLE => 'info',
            self::MULTIPLE => 'warning',
        };
    }
}
