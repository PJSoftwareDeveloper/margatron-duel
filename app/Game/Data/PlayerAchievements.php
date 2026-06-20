<?php

namespace App\Game\Data;

use Illuminate\Support\Collection;

final readonly class PlayerAchievements
{
    /**
     * @param  Collection<int, AchievementProgress>  $entries
     */
    public function __construct(
        public Collection $entries,
        public int $completedCount,
        public int $totalCount,
        public int $overallPercent,
    ) {}
}
