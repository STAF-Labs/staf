<?php

namespace App\Enums\Filter;

use App\Concerns\EnumOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum DimSelectionMode: string implements HasColor, HasLabel
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
