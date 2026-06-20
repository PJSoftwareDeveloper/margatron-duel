<?php

namespace App\Game\Enums;

use App\Game\Attributes\MapMeta;
use ReflectionEnumUnitCase;

enum GameMap: int
{
    #[MapMeta('Ithan', 'map1.png', 1, 10)]
    case Ithan = 1;

    #[MapMeta('Torneg', 'map2.png', 11, 20, 9)]
    case Torneg = 2;

    #[MapMeta('Karka-han', 'map3.png', 21, 30, 20)]
    case KarkaHan = 3;

    #[MapMeta('Werbin', 'map4.png', 31, 40, 30)]
    case Werbin = 4;

    public function meta(): MapMeta
    {
        $reflection = new ReflectionEnumUnitCase(self::class, $this->name);

        return $reflection->getAttributes(MapMeta::class)[0]->newInstance();
    }
}
