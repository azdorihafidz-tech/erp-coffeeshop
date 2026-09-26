<?php

namespace App\Enums;

/**
 * Roastery V2 Minggu 3-4 (2026-09-27) — metode pengolahan buah kopi cherry
 * jadi green bean. Wine/Honey mulai dari tahap Fermentasi (Q3 keputusan
 * Owner, FASE_ROASTERY_V2_DESIGN.md); Washed/Natural langsung ke Drying.
 */
enum ProcessingMethod: string
{
    case Washed         = 'washed';
    case Natural        = 'natural';
    case Honey          = 'honey';
    case WineAnaerobic  = 'wine_anaerobic';

    public function label(): string
    {
        return match ($this) {
            self::Washed        => 'Washed',
            self::Natural       => 'Natural',
            self::Honey         => 'Honey',
            self::WineAnaerobic => 'Wine / Anaerobic',
        };
    }

    /** Wine & Honey butuh tahap fermentasi terkontrol sebelum drying; Washed/Natural langsung drying. */
    public function butuhFermentasi(): bool
    {
        return in_array($this, [self::Honey, self::WineAnaerobic], true);
    }

    /** Kode item Green Bean Arabika hasil method ini (lihat ItemGreenBeanSeeder). */
    public function kodeGreenBeanArabika(): string
    {
        return match ($this) {
            self::Washed        => 'GRB-ARB-WASHED',
            self::Natural       => 'GRB-ARB-NATURAL',
            self::Honey         => 'GRB-ARB-HONEY',
            self::WineAnaerobic => 'GRB-ARB-WINE',
        };
    }
}
