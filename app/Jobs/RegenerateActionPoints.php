<?php

namespace App\Jobs;

use App\Events\ActionPointsChanged;
use App\Game\Services\ActionPointRegenerationScheduler;
use App\Game\Services\GameProfileService;
use App\Models\GameProfile;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RegenerateActionPoints implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $profileId,
    ) {}

    public function handle(GameProfileService $profiles, ActionPointRegenerationScheduler $scheduler): void
    {
        $profile = GameProfile::query()->find($this->profileId);

        if (! $profile) {
            return;
        }

        $previousActionPoints = (int) $profile->pa;
        $profile = $profiles->regenerateActionPoints($profile);

        if ((int) $profile->pa !== $previousActionPoints) {
            broadcast(new ActionPointsChanged(
                (int) $profile->user_id,
                GameProfileService::actionPointState($profile),
            ));
        }

        $scheduler->schedule($profile);
    }

    public function uniqueId(): string
    {
        return (string) $this->profileId;
    }
}
