<?php

namespace App\Concerns;

trait EnumOptions
{
    public function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [
                $case->value => $case->label(),
            ])
            ->toArray();
    }

    public function toArray(): array
    {
        return collect(self::cases())
            ->map(fn (self $case) => [
                'code' => $case->value,
                'label' => $case->label(),
            ])
            ->toArray();
    }
}
