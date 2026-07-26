<?php

namespace App\Game\Services;

use App\Game\Enums\LocationType;
use App\Game\Repositories\StaticGameCatalogRepository;
use App\Models\GameProfile;
use DomainException;

final readonly class GameStateService
{
    /**
     * Prices from the PA shop before applying the level multiplier.
     *
     * @var array<int, int>
     */
    private const array PA_BASE_PRICES = [
        5 => 100,
        10 => 180,
        15 => 250,
    ];

    private const float PA_LEVEL_PRICE_FACTOR = 0.1111;

    public function __construct(
        private StaticGameCatalogRepository $catalog,
        private GameProfileService $profiles,
        private TavernRestService $rests,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(GameProfile $profile): array
    {
        $profile = $this->rests->completeExpired($profile);
        $profile = $this->profiles->recalculate($profile);
        $map = $this->withRuntimeMapData($profile, $this->catalog->map($profile->current_map_id));

        return [
            'profile' => $profile,
            'map' => $map,
            'worldMaps' => $this->worldMaps($profile),
            'shops' => $this->catalog->shops(),
            'paOffers' => $this->paOffers($profile),
            'rest' => $this->rests->state($profile),
        ];
    }

    public function selectMap(GameProfile $profile, int $mapId): GameProfile
    {
        $map = $this->catalog->map($mapId);

        if ($profile->level < $map['requiredLevel']) {
            throw new DomainException('Masz za niski poziom na tę mapę.');
        }

        $profile->current_map_id = $mapId;
        $profile->save();

        return $profile->refresh();
    }

    public function rest(GameProfile $profile, int $minutes): GameProfile
    {
        return $this->rests->start($profile, $minutes);
    }

    public function instantRest(GameProfile $profile): GameProfile
    {
        return $this->rests->instant($profile);
    }

    public function buyPa(GameProfile $profile, int $amount): GameProfile
    {
        $price = $this->calculatePaPrice($profile, $amount);

        if ($profile->gold < $price) {
            throw new DomainException('Masz za mało złota.');
        }

        $profile->forceFill([
            'gold' => $profile->gold - $price,
            'pa' => $profile->pa + $amount,
            'pa_regenerated_at' => now(),
        ])->save();

        return $profile->refresh();
    }

    /**
     * @return array<int, array{amount: int, price: int}>
     */
    public function paOffers(GameProfile $profile): array
    {
        return array_map(
            fn (int $amount): array => [
                'amount' => $amount,
                'price' => $this->calculatePaPrice($profile, $amount),
            ],
            array_keys(self::PA_BASE_PRICES),
        );
    }

    public function unlockedStage(GameProfile $profile, int $mapId, string $locationId): int
    {
        return (int) (($profile->stage_progress ?? [])["{$mapId}_{$locationId}"] ?? 1);
    }

    public function unlockNextStage(GameProfile $profile, int $mapId, string $locationId, int $stage): GameProfile
    {
        $progress = $profile->stage_progress ?? [];
        $key = "{$mapId}_{$locationId}";
        $progress[$key] = max((int) ($progress[$key] ?? 1), min(6, $stage + 1));
        $profile->stage_progress = $progress;
        $profile->save();

        return $profile->refresh();
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<string, mixed>
     */
    private function withRuntimeMapData(GameProfile $profile, array $map): array
    {
        $locations = array_map(function (array $location) use ($profile, $map): array {
            if ($location['type'] !== LocationType::Battle->value) {
                return $location;
            }

            $unlockedStage = $this->unlockedStage($profile, $map['id'], $location['id']);

            return [
                ...$location,
                'unlockedStage' => $unlockedStage,
                'stages' => $this->catalog->stagesForLocation($location, $unlockedStage),
            ];
        }, $map['locations']);

        return [
            ...$map,
            'locations' => $locations,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function worldMaps(GameProfile $profile): array
    {
        $maps = $this->catalog->maps();

        return array_map(function (array $position) use ($maps, $profile): array {
            $map = $maps[(int) $position['id']] ?? null;

            return [
                ...$position,
                'name' => $map['name'] ?? 'Nieodkryta kraina',
                'requiredLevel' => $map['requiredLevel'] ?? 999,
                'locked' => $map === null || $profile->level < $map['requiredLevel'],
                'current' => $map !== null && $profile->current_map_id === $map['id'],
            ];
        }, $this->catalog->worldMapPositions());
    }

    private function calculatePaPrice(GameProfile $profile, int $amount): int
    {
        $basePrice = self::PA_BASE_PRICES[$amount] ?? null;

        if ($basePrice === null) {
            throw new DomainException('Nieprawidłowa ilość PA.');
        }

        return (int) round($basePrice * max(1, $profile->level) * self::PA_LEVEL_PRICE_FACTOR);
    }
}
