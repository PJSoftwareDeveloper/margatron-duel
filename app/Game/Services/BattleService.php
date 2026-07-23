<?php

namespace App\Game\Services;

use App\Game\Enums\ArenaDifficulty;
use App\Game\Enums\ItemRarity;
use App\Game\Enums\LocationType;
use App\Game\Repositories\StaticGameCatalogRepository;
use App\Models\GameProfile;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class BattleService
{
    public function __construct(
        private StaticGameCatalogRepository $catalog,
        private GameProfileService $profiles,
        private GameStateService $gameState,
        private ItemFactory $items,
        private InventoryService $inventory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fightStage(GameProfile $profile, string $locationId, int $stage): array
    {
        return DB::transaction(function () use ($profile, $locationId, $stage): array {
            $profile->refresh();
            $map = $this->catalog->map($profile->current_map_id);
            $location = $this->catalog->location($map['id'], $locationId);

            if (! $location || $location['type'] !== LocationType::Battle->value) {
                throw new DomainException('Nie znaleziono expowiska.');
            }

            if ($profile->level < ($location['levelReq'] ?? 1)) {
                throw new DomainException('Masz za niski poziom na tę lokację.');
            }

            $unlockedStage = $this->gameState->unlockedStage($profile, $map['id'], $locationId);

            if ($stage > $unlockedStage || $stage < 1 || $stage > 5) {
                throw new DomainException('Ten etap nie jest jeszcze odblokowany.');
            }

            $this->spendPa($profile, (int) ($location['pa'] ?? 1));

            $stageData = collect($this->catalog->stagesForLocation($location, $unlockedStage))
                ->first(fn (array $candidate): bool => $candidate['stage'] === $stage);
            $enemyKey = $location['enemies'][array_rand($location['enemies'])];
            $enemy = $this->catalog->scaledEnemy($map['id'], $enemyKey, (int) $stageData['level'], 'enemies');
            $result = $this->runAutoBattle($profile, $enemy, "{$location['name']} - Etap {$stage}");

            if ($result['won']) {
                $this->applyVictory($profile, $enemy, $result);
                $this->gameState->unlockNextStage($profile, $map['id'], $locationId, $stage);
            } else {
                $this->applyDefeat($profile, $result);
            }

            return $result;
        });
    }


    /**
     * @return array<string, mixed>
     */
    public function fightArena(GameProfile $profile, ArenaDifficulty $difficulty): array
    {
        return DB::transaction(function () use ($profile, $difficulty): array {
            $profile->refresh();
            $map = $this->catalog->map($profile->current_map_id);
            $cost = match ($difficulty) {
                ArenaDifficulty::Easy => 1,
                ArenaDifficulty::Medium => 2,
                ArenaDifficulty::Hard => 3,
            };

            $this->spendPa($profile, $cost);

            $enemyKeys = $map['arenaEnemies'][$difficulty->value] ?? [];

            if ($enemyKeys === []) {
                throw new DomainException('Arena na tej mapie jest niedostępna.');
            }

            $enemyKey = $enemyKeys[array_rand($enemyKeys)];
            $level = $map['levelRange']['min'] + $difficulty->levelBonus();
            $enemy = $this->catalog->scaledEnemy($map['id'], $enemyKey, $level, 'enemies');
            $result = $this->runAutoBattle($profile, $enemy, "Arena - {$difficulty->label()} Walka", $difficulty);

            if ($result['won']) {
                $this->applyVictory($profile, $enemy, $result, $difficulty);
            } else {
                $this->applyDefeat($profile, $result);
            }

            return $result;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function fightToughEnemy(GameProfile $profile, string $locationId, string $enemyType): array
    {
        return DB::transaction(function () use ($profile, $locationId, $enemyType): array {
            $profile->refresh();
            $map = $this->catalog->map($profile->current_map_id);
            $location = $this->catalog->location($map['id'], $locationId);

            if (! $location || $location['type'] !== LocationType::ToughEnemy->value) {
                throw new DomainException('Nie znaleziono przeciwnika.');
            }

            $this->spendPa($profile, (int) ($location['pa'] ?? 1));

            $enemyTag = 'none';
            $difficulty = ArenaDifficulty::Easy;
            switch ($enemyType) {
                case 'elite':
                    $enemyTag = 'eliteEnemies';
                    $level = $map['levelRange']['min'];
                    $difficulty = ArenaDifficulty::Easy;
                    break;
                case 'elite2':
                    $enemyTag = 'elite2Enemies';
                    $level = $map['levelRange']['min'] + 5;
                    $difficulty = ArenaDifficulty::Medium;
                    break;
                case 'hero':
                    $enemyTag = 'heroEnemies';
                    $level = $map['levelRange']['max'];
                    $difficulty = ArenaDifficulty::Hard;
                    break;
            }
            $enemyKeys = array_keys($map[$enemyTag]);
            if ($enemyKeys === []) {
                throw new DomainException('Walka z przeciwnikiem na tej mapie jest niedostępna.');
            }
            $enemyKey = $enemyKeys[array_rand($enemyKeys)];
            $enemy = $this->catalog->scaledEnemy($map['id'], $enemyKey, $level, $enemyTag);
            $result = $this->runAutoBattle($profile, $enemy, 'Walka z silnym przeciwnikiem');

            if ($result['won']) {
                $this->applyVictory($profile, $enemy, $result, $difficulty);
            } else {
                $this->applyDefeat($profile, $result);
            }

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $enemy
     * @return array<string, mixed>
     */
    private function runAutoBattle(GameProfile $profile, array $enemy, string $name, ?ArenaDifficulty $arenaDifficulty = null): array
    {
        $profile = $this->profiles->recalculate($profile);
        $playerHp = max(1, $profile->hp);
        $enemyHp = (int) $enemy['hp'];
        $log = [
            [
                'type' => 'battle-start',
                'enemyName' => (string) $enemy['name'],
            ],
        ];

        for ($turn = 1; $turn <= 100; $turn++) {
            $playerDmg = random_int($profile->dmg_min, max($profile->dmg_min, $profile->dmg_max));
            $isCrit = $this->percentRoll() < $profile->crit_chance;
            $finalDmg = $isCrit ? (int) floor($playerDmg * ($profile->crit_power / 100)) : $playerDmg;
            $enemyHp = max(0, $enemyHp - $finalDmg);
            $log[] = [
                'type' => 'attack',
                'actor' => 'player',
                'target' => 'enemy',
                'targetName' => (string) $enemy['name'],
                'attackPower' => $playerDmg,
                'damage' => $finalDmg,
                'remainingHp' => $enemyHp,
                'critical' => $isCrit,
            ];

            if ($enemyHp <= 0) {
                return $this->result($name, $enemy, true, $playerHp, $enemyHp, $log, $arenaDifficulty);
            }

            $enemyDmg = random_int((int) $enemy['dmgMin'], max((int) $enemy['dmgMin'], (int) $enemy['dmgMax']));
            $dodged = $this->percentRoll() < $profile->dodge;

            if ($dodged) {
                $log[] = [
                    'type' => 'dodge',
                    'actor' => 'player',
                    'attacker' => 'enemy',
                    'attackerName' => (string) $enemy['name'],
                ];
            } else {
                $reducedDmg = max(0, $enemyDmg - (int) floor($profile->armor));
                $playerHp = max(0, $playerHp - $reducedDmg);
                $log[] = [
                    'type' => 'attack',
                    'actor' => 'enemy',
                    'actorName' => (string) $enemy['name'],
                    'target' => 'player',
                    'attackPower' => $enemyDmg,
                    'armor' => (int) $profile->armor,
                    'damage' => $reducedDmg,
                    'remainingHp' => $playerHp,
                    'critical' => false,
                ];
            }

            if ($playerHp <= 0) {
                return $this->result($name, $enemy, false, $playerHp, $enemyHp, $log, $arenaDifficulty);
            }
        }

        return $this->result($name, $enemy, false, $playerHp, $enemyHp, $log, $arenaDifficulty);
    }

    /**
     * @param  array<string, mixed>  $enemy
     * @param  array<int, array<string, mixed>>  $log
     * @return array<string, mixed>
     */
    private function result(string $name, array $enemy, bool $won, int $playerHp, int $enemyHp, array $log, ?ArenaDifficulty $arenaDifficulty): array
    {
        return [
            'name' => $name,
            'enemy' => $enemy,
            'won' => $won,
            'playerHp' => $playerHp,
            'enemyHp' => $enemyHp,
            'arenaDifficulty' => $arenaDifficulty?->value,
            'log' => $log,
            'rewards' => [
                'exp' => $won ? $enemy['exp'] : 0,
                'gold' => $won ? $enemy['gold'] : 0,
                'level' => null,
                'drop' => null,
                'dropAdded' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $enemy
     * @param  array<string, mixed>  $result
     */
    private function applyVictory(GameProfile $profile, array $enemy, array &$result, ?ArenaDifficulty $arenaDifficulty = null): void
    {
        $profile->forceFill([
            'gold' => $profile->gold + $enemy['gold'],
            'hp' => $result['playerHp'],
            'monsters_killed' => $profile->monsters_killed + 1,
        ])->save();

        $levelResult = $this->profiles->addExperience($profile, (int) $enemy['exp']);
        $drop = $this->items->rollForDrop((int) $enemy['level'], (int) $profile->luck, $arenaDifficulty);
        $dropAdded = $drop ? $this->inventory->addItem($profile, $drop) : false;

        if ($dropAdded && $drop) {
            $this->recordItemFound($profile, $drop);
        }

        $result['rewards']['level'] = $levelResult;
        $result['rewards']['drop'] = $drop;
        $result['rewards']['dropAdded'] = $dropAdded;
        $result['log'][] = [
            'type' => 'reward',
            'rewardType' => 'experience',
            'amount' => (int) $enemy['exp'],
        ];

        if ($levelResult['leveledUp']) {
            $result['log'][] = [
                'type' => 'level-up',
                'level' => (int) $levelResult['newLevel'],
            ];
            $result['log'][] = [
                'type' => 'attribute-points',
                'levelsGained' => (int) $levelResult['levelsGained'],
                'points' => (int) $levelResult['levelsGained'] * 2,
            ];
        }

        if ($drop) {
            $result['log'][] = [
                'type' => 'drop',
                'itemName' => (string) $drop['name'],
                'color' => (string) $drop['rarityColor'],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function applyDefeat(GameProfile $profile, array &$result): void
    {
        $profile->hp = $profile->hp_max;
        $profile->save();
        $result['log'][] = ['type' => 'defeat'];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function recordItemFound(GameProfile $profile, array $item): void
    {
        $rarity = ItemRarity::tryFrom((string) ($item['rarity'] ?? ''));

        match ($rarity) {
            ItemRarity::Unique => $profile->increment('unique_items_found'),
            ItemRarity::Heroic => $profile->increment('heroic_items_found'),
            ItemRarity::Legendary => $profile->increment('legendary_items_found'),
            default => null,
        };
    }

    private function spendPa(GameProfile $profile, int $amount): void
    {
        if ($profile->pa < $amount) {
            throw new DomainException('Masz za mało PA.');
        }

        $profile->pa -= $amount;
        $profile->pa_regenerated_at = now();
        $profile->save();
    }

    private function percentRoll(): float
    {
        return random_int(1, 10_000) / 100;
    }
}
