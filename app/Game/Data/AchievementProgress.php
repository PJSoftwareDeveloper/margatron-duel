<?php

namespace App\Game\Data;

final readonly class AchievementProgress
{
    public function __construct(
        public string $id,
        public string $label,
        public string $icon,
        public int $value,
        public int $target,
        public int $percent,
        public string $progressLabel,
        public bool $completed,
    ) {}
}
