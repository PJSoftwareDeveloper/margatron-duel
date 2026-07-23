<?php

namespace App\Game\Enums;

use App\Game\Attributes\MapMeta;
use ReflectionEnumUnitCase;

enum GameMap: int
{
    #[MapMeta('Ithan', 'maps/ithan.png', 1, 10)]
    case Ithan = 1;

    #[MapMeta('Torneg', 'maps/torneg.png', 11, 20, 9)]
    case Torneg = 2;

    #[MapMeta('Karka-han', 'maps/karka-han.png', 21, 30, 20)]
    case KarkaHan = 3;

    #[MapMeta('Werbin', 'maps/werbin.png', 31, 40, 30)]
    case Werbin = 4;

    public function meta(): MapMeta
    {
        $reflection = new ReflectionEnumUnitCase(self::class, $this->name);

        return $reflection->getAttributes(MapMeta::class)[0]->newInstance();
    }
}
