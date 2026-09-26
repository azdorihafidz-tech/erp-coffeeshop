<?php

namespace App\Enums;

enum PackSize: string
{
    case Size250g = '250g';
    case Size500g = '500g';
    case Size1kg  = '1kg';

    public function label(): string
    {
        return match ($this) {
            self::Size250g => '250 gram',
            self::Size500g => '500 gram',
            self::Size1kg  => '1 kg',
        };
    }

    public function kg(): float
    {
        return match ($this) {
            self::Size250g => 0.25,
            self::Size500g => 0.5,
            self::Size1kg  => 1.0,
        };
    }
}
