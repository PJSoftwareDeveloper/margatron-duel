<?php

namespace App\Game\Repositories;

use App\Game\Enums\ArenaDifficulty;
use App\Models\GameProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class ArenaPlayerRepository
{
    /**
     * @return Collection<int, GameProfile>
     */
    public function players(GameProfile $profile): Collection
    {
        return $this->levelOrderedQuery()
            ->with('user')
            ->get();
    }

    public function randomPlayer(GameProfile $profile, ArenaDifficulty $difficulty): ?GameProfile
    {
        [$min, $max] = $this->levelRangeForDifficulty($profile->level, $difficulty);

        $min = max(1, $min);

        $globalMax = GameProfile::max('level');
        $max = min($globalMax, $max);

        $enemy = GameProfile::query()
            ->whereBetween('level', [$min, $max])
            ->where('id', '!=', $profile->id)
            ->inRandomOrder()
            ->first();

        if ($enemy) {
            return $enemy;
        }

        return GameProfile::query()
            ->where('id', '!=', $profile->id)
            ->orderByRaw('ABS(level - ?)', [$profile->level])
            ->first();
    }



    private function levelRangeForDifficulty(int $level, ArenaDifficulty $difficulty): array
    {
        return match ($difficulty) {
            ArenaDifficulty::Easy => [$level - 25, $level + 25],
            ArenaDifficulty::Medium => [$level - 10, $level + 10],
            ArenaDifficulty::Hard => [$level, $level + 25],
            default => [$level - 25, $level + 25],
        };
    }
    
}

?>