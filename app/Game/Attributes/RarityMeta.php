<?php

namespace App\Game\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class RarityMeta
{
    public function __construct(
        public string $label,
        public string $color,
        public string $cssClass,
        public float $statMultiplier,
        public float $priceMultiplier,
        public int $bonusStats,
    ) {}
}
