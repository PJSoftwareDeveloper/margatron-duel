<?php

namespace App\Game\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class MapMeta
{
    public function __construct(
        public string $name,
        public string $image,
        public int $levelMin,
        public int $levelMax,
        public int $requiredLevel = 1,
    ) {}
}
