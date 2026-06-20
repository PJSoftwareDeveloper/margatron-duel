<?php

namespace App\Game\Enums;

enum PlayerAttribute: string
{
    case Vitality = 'vitality';
    case Strength = 'strength';
    case Luck = 'luck';

    public function label(): string
    {
        return match ($this) {
            self::Vitality => 'Witalność',
            self::Strength => 'Siła',
            self::Luck => 'Szczęście',
        };
    }
}
