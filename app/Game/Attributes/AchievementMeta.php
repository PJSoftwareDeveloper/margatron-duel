<?php

namespace App\Game\Attributes;

use App\Game\Enums\AchievementMetric;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class AchievementMeta
{
    public function __construct(
        public string $label,
        public AchievementMetric $metric,
        public int $target,
        public string $icon,
        public string $unit = '',
    ) {}
}
