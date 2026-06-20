<?php

namespace App\Game\Services;

use App\Jobs\RegenerateActionPoints;
use App\Models\GameProfile;
use Throwable;

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

        try {
            RegenerateActionPoints::dispatch((int) $profile->getKey())
                ->onQueue('action-points')
                ->delay($nextRegenerationAt);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
