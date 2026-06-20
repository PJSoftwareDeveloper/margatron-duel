<?php

namespace App\Game\Enums;

enum ArenaDifficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::Easy => 'Łatwa',
            self::Medium => 'Średnia',
            self::Hard => 'Trudna',
        };
    }

    public function levelBonus(): int
    {
        return match ($this) {
            self::Easy => 0,
            self::Medium => 3,
            self::Hard => 6,
        };
    }

    public function dropRate(): float
    {
        return match ($this) {
            self::Easy => 1.0,
            self::Medium => 1.3,
            self::Hard => 1.6,
        };
    }

    public function rarityBonus(): int
    {
        return match ($this) {
            self::Easy => 0,
            self::Medium => 5,
            self::Hard => 15,
        };
    }
}
