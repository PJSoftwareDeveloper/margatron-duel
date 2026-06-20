<?php

namespace App\Game\Services;

use App\Jobs\RegenerateActionPoints;
use App\Models\GameProfile;

final readonly class ActionPointRegenerationScheduler
{
    public function schedule(GameProfile $profile): void
    {
        if (config('queue.default') === 'sync') {
            return;
        }

        if ((int) $profile->pa >= GameProfileService::actionPointRegenerationLimit()) {
            return;
        }

        $nextRegenerationAt = ($profile->pa_regenerated_at ?? now())
            ->copy()
            ->addSeconds(GameProfileService::actionPointRegenerationSeconds());

        RegenerateActionPoints::dispatch((int) $profile->getKey())
            ->onQueue('action-points')
            ->delay($nextRegenerationAt);
    }
}
