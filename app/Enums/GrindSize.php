<?php

namespace App\Enums;

enum GrindSize: string
{
    case ExtraCoarse = 'extra_coarse';
    case Coarse       = 'coarse';
    case Medium        = 'medium';
    case Fine           = 'fine';
    case ExtraFine       = 'extra_fine';

    public function label(): string
    {
        return match ($this) {
            self::ExtraCoarse => 'Extra Coarse',
            self::Coarse      => 'Coarse',
            self::Medium      => 'Medium',
            self::Fine        => 'Fine',
            self::ExtraFine   => 'Extra Fine',
        };
    }
}
