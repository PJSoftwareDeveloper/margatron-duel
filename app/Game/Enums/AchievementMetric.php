<?php

namespace App\Game\Enums;

enum AchievementMetric: string
{
    case Level = 'level';
    case PlayedSeconds = 'played_seconds';
    case VitalityAssigned = 'vitality_assigned';
    case StrengthAssigned = 'strength_assigned';
    case LuckAssigned = 'luck_assigned';
    case Damage = 'damage';
    case Armor = 'armor';
    case Stun = 'stun';
    case MonstersKilled = 'monsters_killed';
    case UniqueItemsFound = 'unique_items_found';
    case HeroicItemsFound = 'heroic_items_found';
    case LegendaryItemsFound = 'legendary_items_found';
}
