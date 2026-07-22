<?php

namespace App\Game\Enums;

enum LocationType: string
{
    case Battle = 'battle';
    case Arena = 'arena';
    case ToughEnemy = 'toughenemy';
    case Shop = 'shop';
    case Rest = 'rest';
    case WorldMap = 'worldmap';
}
