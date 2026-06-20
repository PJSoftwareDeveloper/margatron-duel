<?php

namespace App\Game\Services;

use App\Jobs\CompleteRest;
use App\Models\GameProfile;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class TavernRestService
{
    /**
     * @return array<string, mixed>
     */
    public function state(GameProfile $profile, ?Carbon $now = null): array
    {
        $now ??= now();
        $tasks = $this->tasks($profile);

        return [
            'options' => array_map(
                fn (int $minutes): array => $this->optionState($minutes, $tasks[(string) $minutes] ?? null, $now),
                $this->optionMinutes(),
            ),
            'instant' => [
                'goldPrice' => $this->instantGoldPrice(),
                'targetActionPoints' => GameProfileService::actionPointRegenerationLimit(),
            ],
        ];
    }

    public function start(GameProfile $profile, int $minutes, ?Carbon $now = null): GameProfile
    {
        $now ??= now();
        $option = $this->option($minutes);
        $completionAt = null;

        $profile = DB::transaction(function () use ($profile, $minutes, $option, $now, &$completionAt): GameProfile {
            /** @var GameProfile $lockedProfile */
            $lockedProfile = GameProfile::query()->lockForUpdate()->findOrFail($profile->getKey());
            $lockedProfile = $this->completeExpiredForLockedProfile($lockedProfile, $now);
            $tasks = $this->tasks($lockedProfile);
            $key = (string) $minutes;

            if (($tasks[$key] ?? null) && $this->isActive($tasks[$key], $now)) {
                throw new DomainException('Ten odpoczynek już trwa.');
            }

            $completionAt = $now->copy()->addSeconds($option['duration_seconds']);
            $tasks[$key] = [
                'minutes' => $minutes,
                'action_points' => $option['action_points'],
                'ends_at' => $completionAt->toIso8601String(),
            ];

            $lockedProfile->forceFill([
                'rest_tasks' => $tasks,
            ])->save();

            return $lockedProfile->refresh();
        });

        $this->dispatchCompletion($profile, $minutes, $completionAt);

        return $profile;
    }

    public function completeDue(GameProfile $profile, int $minutes, ?Carbon $now = null): ?GameProfile
    {
        $now ??= now();
        $completed = false;

        $profile = DB::transaction(function () use ($profile, $minutes, $now, &$completed): ?GameProfile {
            /** @var GameProfile|null $lockedProfile */
            $lockedProfile = GameProfile::query()->lockForUpdate()->find($profile->getKey());

            if (! $lockedProfile) {
                return null;
            }

            $tasks = $this->tasks($lockedProfile);
            $key = (string) $minutes;
            $task = $tasks[$key] ?? null;

            if (! $task || $this->isActive($task, $now)) {
                return null;
            }

            unset($tasks[$key]);

            $lockedProfile->forceFill([
                'pa' => max(0, (int) $lockedProfile->pa) + $this->taskActionPoints($task, $minutes),
                'pa_regenerated_at' => $now,
                'rest_tasks' => $tasks,
            ])->save();

            $completed = true;

            return $lockedProfile->refresh();
        });

        return $completed ? $profile : null;
    }

    public function completeExpired(GameProfile $profile, ?Carbon $now = null): GameProfile
    {
        $now ??= now();

        return DB::transaction(function () use ($profile, $now): GameProfile {
            /** @var GameProfile $lockedProfile */
            $lockedProfile = GameProfile::query()->lockForUpdate()->findOrFail($profile->getKey());

            return $this->completeExpiredForLockedProfile($lockedProfile, $now)->refresh();
        });
    }

    public function instant(GameProfile $profile, ?Carbon $now = null): GameProfile
    {
        $now ??= now();
        $price = $this->instantGoldPrice();

        if ($profile->gold < $price) {
            throw new DomainException('Masz za mało złota.');
        }

        $profile->forceFill([
            'gold' => $profile->gold - $price,
            'pa' => max($profile->pa, GameProfileService::actionPointRegenerationLimit()),
            'pa_regenerated_at' => $now,
        ])->save();

        return $profile->refresh();
    }

    /**
     * @return array<int, int>
     */
    private function optionMinutes(): array
    {
        $minutes = array_map('intval', array_keys((array) config('game.rest.options', [])));
        sort($minutes);

        return $minutes;
    }

    /**
     * @return array{duration_seconds: int, action_points: int}
     */
    private function option(int $minutes): array
    {
        $option = config("game.rest.options.{$minutes}");

        if (! is_array($option)) {
            throw new DomainException('Nieznana opcja odpoczynku.');
        }

        return [
            'duration_seconds' => max(1, (int) ($option['duration_seconds'] ?? ($minutes * 60))),
            'action_points' => max(0, (int) ($option['action_points'] ?? 0)),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $task
     * @return array<string, mixed>
     */
    private function optionState(int $minutes, ?array $task, Carbon $now): array
    {
        $option = $this->option($minutes);
        $endsAt = $task['ends_at'] ?? null;
        $active = $task !== null && $this->isActive($task, $now);

        return [
            'minutes' => $minutes,
            'durationSeconds' => $option['duration_seconds'],
            'actionPoints' => $option['action_points'],
            'active' => $active,
            'endsAt' => $active && is_string($endsAt) ? $endsAt : null,
            'remainingSeconds' => $active ? $this->remainingSeconds($task, $now) : 0,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function tasks(GameProfile $profile): array
    {
        return array_filter(
            $profile->rest_tasks ?? [],
            fn (mixed $task): bool => is_array($task),
        );
    }

    private function completeExpiredForLockedProfile(GameProfile $profile, Carbon $now): GameProfile
    {
        $tasks = $this->tasks($profile);
        $actionPoints = 0;
        $changed = false;

        foreach ($tasks as $key => $task) {
            if ($this->isActive($task, $now)) {
                continue;
            }

            $actionPoints += $this->taskActionPoints($task, (int) $key);
            unset($tasks[$key]);
            $changed = true;
        }

        if ($changed) {
            $profile->forceFill([
                'pa' => max(0, (int) $profile->pa) + $actionPoints,
                'pa_regenerated_at' => $now,
                'rest_tasks' => $tasks,
            ])->save();
        }

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function isActive(array $task, Carbon $now): bool
    {
        return $this->endsAt($task)?->greaterThan($now) ?? false;
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function remainingSeconds(array $task, Carbon $now): int
    {
        $endsAt = $this->endsAt($task);

        if (! $endsAt) {
            return 0;
        }

        return max(0, $endsAt->getTimestamp() - $now->getTimestamp());
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function endsAt(array $task): ?Carbon
    {
        $endsAt = $task['ends_at'] ?? null;

        if (! is_string($endsAt)) {
            return null;
        }

        return Carbon::parse($endsAt);
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function taskActionPoints(array $task, int $minutes): int
    {
        return max(0, (int) ($task['action_points'] ?? $this->option($minutes)['action_points']));
    }

    private function dispatchCompletion(GameProfile $profile, int $minutes, ?Carbon $completionAt): void
    {
        if (! $completionAt || config('queue.default') === 'sync') {
            return;
        }

        try {
            CompleteRest::dispatch((int) $profile->getKey(), $minutes)
                ->onQueue('action-points')
                ->delay($completionAt);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function instantGoldPrice(): int
    {
        return max(0, (int) config('game.rest.instant.gold_price', 500));
    }
}
