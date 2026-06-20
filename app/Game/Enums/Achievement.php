<?php

namespace App\Game\Enums;

use App\Game\Attributes\AchievementMeta;
use ReflectionEnumUnitCase;

enum Achievement: string
{
    #[AchievementMeta('Osiągnij poziom 20', AchievementMetric::Level, 20, '⚜')]
    case ReachLevel20 = 'reach_level_20';

    #[AchievementMeta('Graj w grę 5h', AchievementMetric::PlayedSeconds, 18_000, '⏳', 's')]
    case PlayFiveHours = 'play_five_hours';

    #[AchievementMeta('Przydziel 10p w witalność', AchievementMetric::VitalityAssigned, 10, '♥')]
    case AssignVitality10 = 'assign_vitality_10';

    #[AchievementMeta('Przydziel 10p w siłę', AchievementMetric::StrengthAssigned, 10, '✊')]
    case AssignStrength10 = 'assign_strength_10';

    #[AchievementMeta('Przydziel 5p w szczęście', AchievementMetric::LuckAssigned, 5, '☘')]
    case AssignLuck5 = 'assign_luck_5';

    #[AchievementMeta('Uzyskaj 20p obrażeń', AchievementMetric::Damage, 20, '⚔')]
    case ReachDamage20 = 'reach_damage_20';

    #[AchievementMeta('Uzyskaj 10p pancerza', AchievementMetric::Armor, 10, '🛡')]
    case ReachArmor10 = 'reach_armor_10';

    #[AchievementMeta('Uzyskaj 32% ogłuszenia', AchievementMetric::Stun, 32, '☠', '%')]
    case ReachStun32 = 'reach_stun_32';

    #[AchievementMeta('Zabij 1000 potworów', AchievementMetric::MonstersKilled, 1000, '⚔')]
    case KillMonsters1000 = 'kill_monsters_1000';

    #[AchievementMeta('Zdobądź 100 przedmiotów unikalnych', AchievementMetric::UniqueItemsFound, 100, '✳')]
    case FindUniqueItems100 = 'find_unique_items_100';

    #[AchievementMeta('Zdobądź 10 przedmiotów heroicznych', AchievementMetric::HeroicItemsFound, 10, '✦')]
    case FindHeroicItems10 = 'find_heroic_items_10';

    #[AchievementMeta('Zdobądź 5 przedmiotów legendarnych', AchievementMetric::LegendaryItemsFound, 5, '✺')]
    case FindLegendaryItems5 = 'find_legendary_items_5';

    public function meta(): AchievementMeta
    {
        $reflection = new ReflectionEnumUnitCase(self::class, $this->name);

        return $reflection->getAttributes(AchievementMeta::class)[0]->newInstance();
    }
}
