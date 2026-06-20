<?php

namespace App\Game\Enums;

use App\Game\Attributes\RarityMeta;
use ReflectionEnumUnitCase;

enum ItemRarity: string
{
    #[RarityMeta('Zwykły', '#ffffff', '', 1.0, 1.0, 0)]
    case Common = 'common';

    #[RarityMeta('Unikalny', '#66cc66', 'unique', 1.3, 2.5, 1)]
    case Unique = 'unique';

    #[RarityMeta('Heroiczny', '#2090fe', 'heroic', 1.6, 5.0, 2)]
    case Heroic = 'heroic';

    #[RarityMeta('Legendarny', '#fa9a20', 'legendary', 2.0, 10.0, 3)]
    case Legendary = 'legendary';

    public function meta(): RarityMeta
    {
        $reflection = new ReflectionEnumUnitCase(self::class, $this->name);

        return $reflection->getAttributes(RarityMeta::class)[0]->newInstance();
    }
}
