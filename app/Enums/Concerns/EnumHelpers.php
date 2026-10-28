<?php

namespace App\Enums\Concerns;

trait EnumHelpers
{
    /** Untuk Select::options() */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->all();
    }

    /** Untuk Rule::in() dan validasi */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}