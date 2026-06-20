<?php

namespace App\Game\Enums;

enum ItemType: string
{
    case Weapon = 'weapon';
    case Armor = 'armor';
    case Talisman = 'talisman';
    case Potion = 'potion';

    public function label(): string
    {
        return match ($this) {
            self::Weapon => 'Broń',
            self::Armor => 'Zbroja',
            self::Talisman => 'Talizman',
            self::Potion => 'Mikstura',
        };
    }
}
