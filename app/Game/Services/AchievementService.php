<?php

namespace App\Game\Services;

use App\Game\Data\AchievementProgress;
use App\Game\Data\PlayerAchievements;
use App\Game\Enums\Achievement;
use App\Game\Enums\AchievementMetric;
use App\Models\GameProfile;

final readonly class AchievementService
{
    public function forProfile(GameProfile $profile): PlayerAchievements
    {
        $entries = collect(Achievement::cases())
            ->map(fn (Achievement $achievement): AchievementProgress => $this->progress($achievement, $profile));
        $completedCount = $entries->filter(fn (AchievementProgress $entry): bool => $entry->completed)->count();

        return new PlayerAchievements(
            entries: $entries,
            completedCount: $completedCount,
            totalCount: $entries->count(),
            overallPercent: (int) floor($entries->avg('percent') ?? 0),
        );
    }

    private function progress(Achievement $achievement, GameProfile $profile): AchievementProgress
    {
        $meta = $achievement->meta();
        $value = $this->value($meta->metric, $profile);
        $target = max(1, $meta->target);
        $percent = min(100, (int) floor(($value / $target) * 100));
        $completed = $value >= $target;

        return new AchievementProgress(
            id: $achievement->value,
            label: $meta->label,
            icon: $meta->icon,
            value: $value,
            target: $target,
            percent: $percent,
            progressLabel: $completed
                ? 'Osiągnięcie ukończone'
                : "Następne osiągnięcie przy {$this->formatValue($target, $meta->unit)} ({$percent}%)",
            completed: $completed,
        );
    }

    private function value(AchievementMetric $metric, GameProfile $profile): int
    {
        return match ($metric) {
            AchievementMetric::Level => $profile->level,
            AchievementMetric::PlayedSeconds => $profile->played_seconds,
            AchievementMetric::VitalityAssigned => $profile->vitality_points_assigned,
            AchievementMetric::StrengthAssigned => $profile->strength_points_assigned,
            AchievementMetric::LuckAssigned => $profile->luck_points_assigned,
            AchievementMetric::Damage => $profile->dmg_max,
            AchievementMetric::Armor => $profile->armor,
            AchievementMetric::Stun => (int) floor($profile->stun),
            AchievementMetric::MonstersKilled => $profile->monsters_killed,
            AchievementMetric::UniqueItemsFound => $profile->unique_items_found,
            AchievementMetric::HeroicItemsFound => $profile->heroic_items_found,
            AchievementMetric::LegendaryItemsFound => $profile->legendary_items_found,
        };
    }

    private function formatValue(int $value, string $unit): string
    {
        if ($unit === 's') {
            $hours = intdiv($value, 3600);
            $minutes = intdiv($value % 3600, 60);

            if ($hours > 0) {
                return "{$hours}h {$minutes}m";
            }

            return "{$minutes}m";
        }

        return $unit === '' ? (string) $value : "{$value}{$unit}";
    }
}
