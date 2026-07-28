<?php

namespace App\Game\Data;

final readonly class PlayerRankingEntry
{
    public function __construct(
        public int $position,
        public int $profileId,
        public int $userId,
        public string $nick,
        public int $level,

        public bool $currentUser = false,
    ) {}
}
