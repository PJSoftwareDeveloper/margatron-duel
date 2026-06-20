<?php

namespace App\Jobs;

use App\Events\ActionPointsChanged;
use App\Game\Services\ActionPointRegenerationScheduler;
use App\Game\Services\GameProfileService;
use App\Game\Services\TavernRestService;
use App\Models\GameProfile;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CompleteRest implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $profileId,
        public readonly int $minutes,
    ) {}

    public function handle(TavernRestService $rests, ActionPointRegenerationScheduler $scheduler): void
    {
        $profile = GameProfile::query()->find($this->profileId);

        if (! $profile) {
            return;
        }

        $profile = $rests->completeDue($profile, $this->minutes);

        if (! $profile) {
            return;
        }

        broadcast(new ActionPointsChanged(
            (int) $profile->user_id,
            GameProfileService::actionPointState($profile),
        ));

        $scheduler->schedule($profile);
    }

    public function uniqueId(): string
    {
        return "{$this->profileId}:{$this->minutes}";
    }
}
