<?php

namespace App\Game\Services;

use App\Game\Enums\ArenaDifficulty;
use App\Game\Enums\ItemRarity;
use App\Game\Enums\ItemType;
use App\Game\Repositories\StaticGameCatalogRepository;

final readonly class ItemFactory
{
    public function __construct(
        private StaticGameCatalogRepository $catalog,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function rollForDrop(int $enemyLevel, int $playerLuck = 0, ?ArenaDifficulty $arenaDifficulty = null): ?array
    {
        $dropChance = min(85, (40 + $playerLuck) * ($arenaDifficulty?->dropRate() ?? 1));
        $percentRollValue = $this->percentRoll();
        if ($percentRollValue > $dropChance) {
            return null;
        }

        return $this->generate($enemyLevel, luckBonus: $playerLuck, arenaDifficulty: $arenaDifficulty);
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(
        int $level,
        ?ItemRarity $forcedRarity = null,
        ?ItemType $forcedType = null,
        int $luckBonus = 0,
        ?ArenaDifficulty $arenaDifficulty = null,
    ): array {
        $rarity = $forcedRarity ?? $this->rollRarity($luckBonus, $arenaDifficulty);
        $type = $forcedType ?? $this->randomCase(ItemType::cases());
        $base = $this->random($this->catalog->itemBases()[$type->value]);
        $meta = $rarity->meta();
        $prefixes = $this->catalog->rarityPrefixes()[$rarity->value] ?? [];
        $prefix = $rarity === ItemRarity::Common || $prefixes === [] ? '' : $this->random($prefixes).' ';
        $stats = [];
        $effect = null;

        if ($type === ItemType::Weapon) {
            $scale = 1 + ($level * 0.15);
            $stats = [
                'dmgMin' => max(1, (int) floor($base['dmgMin'] * $scale * $meta->statMultiplier)),
                'dmgMax' => max(2, (int) floor($base['dmgMax'] * $scale * $meta->statMultiplier)),
            ];
            $bonusStats = $this->generateBonusStats($type, $meta->bonusStats, $level, $meta->statMultiplier);
            $stats = [...$stats, ...$this->flattenBonusStats($bonusStats)];
        } elseif ($type === ItemType::Armor) {
            $scale = 1 + ($level * 0.12);
            $stats = [
                'armor' => max(1, (int) floor($base['armor'] * $scale * $meta->statMultiplier)),
            ];
            $bonusStats = $this->generateBonusStats($type, $meta->bonusStats, $level, $meta->statMultiplier);
            $stats = [...$stats, ...$this->flattenBonusStats($bonusStats)];
        } elseif ($type === ItemType::Talisman) {
            $bonusStats = $this->generateBonusStats($type, max(1, $meta->bonusStats), $level, $meta->statMultiplier);
            $stats = $this->flattenBonusStats($bonusStats);
        } else {
            $scale = 1 + ($level * 0.1);
            $effect = $base['effect'];
            $effect['value'] = max(1, (int) floor($effect['value'] * $scale * $meta->statMultiplier));
            $bonusStats = [];
        }

        $power = $this->power($type, $stats, $effect, $meta->statMultiplier);
        $price = (int) floor($power * $meta->priceMultiplier);

        return [
            'id' => 'drop_'.bin2hex(random_bytes(8)),
            'name' => $prefix.$base['name'],
            'icon' => $base['image'],
            'image' => $base['image'],
            'imageUrl' => "/game-assets/{$base['image']}",
            'type' => $type->value,
            'itemType' => $type->value,
            'itemTypeName' => $type->label(),
            'rarity' => $rarity->value,
            'rarityName' => $meta->label,
            'rarityColor' => $meta->color,
            'rarityCss' => $meta->cssClass,
            'level' => $level,
            'stats' => $stats,
            'bonusStats' => $bonusStats,
            'effect' => $effect['type'] ?? null,
            'effectValue' => $effect['value'] ?? null,
            'effectData' => $effect,
            'power' => $power,
            'price' => $price,
            'quantity' => 1,
            ...$stats,
        ];
    }

    private function rollRarity(int $luckBonus, ?ArenaDifficulty $arenaDifficulty): ItemRarity
    {
        $chances = $this->catalog->baseDropChances();
        $luckMod = $luckBonus / 2;
        $arenaBonus = $arenaDifficulty?->rarityBonus() ?? 0;

        $legendaryChance = $chances[ItemRarity::Legendary->value] + ($luckMod * 0.1) + ($arenaBonus * 0.2);
        $heroicChance = $chances[ItemRarity::Heroic->value] + $luckMod * 0.3 + ($arenaBonus * 0.3) - $legendaryChance;
        $uniqueChance = $chances[ItemRarity::Unique->value] + $luckMod * 0.6 + ($arenaBonus * 0.5) - $heroicChance - $legendaryChance;
        $commonChance = 100 - $uniqueChance - $heroicChance - $legendaryChance;

        $chances[ItemRarity::Legendary->value] = $legendaryChance;
        $chances[ItemRarity::Heroic->value] = $heroicChance;
        $chances[ItemRarity::Unique->value] = $uniqueChance;
        $chances[ItemRarity::Common->value] = $commonChance;
        logger()->info($chances);
        logger()->info("Arena bonus: $arenaBonus");
        logger()->info("Luck modifier: $luckMod");
        $roll = $this->percentRoll();
        $cursor = 0.0;

        foreach ([ItemRarity::Common, ItemRarity::Unique, ItemRarity::Heroic, ItemRarity::Legendary] as $rarity) {
            $cursor += $chances[$rarity->value];

            if ($roll <= $cursor) {
                return $rarity;
            }
        }

        return ItemRarity::Common;
    }

    /**
     * @return array<string, array{value: int, name: string, suffix: string}>
     */
    private function generateBonusStats(ItemType $itemType, int $count, int $level, float $rarityMultiplier): array
    {
        if ($count <= 0) {
            return [];
        }

        $possible = match ($itemType) {
            ItemType::Weapon => [
                ['key' => 'critChance', 'name' => 'Szansa krytyka', 'suffix' => '%', 'min' => 1, 'max' => 5],
                ['key' => 'critPower', 'name' => 'Moc krytyka', 'suffix' => '%', 'min' => 5, 'max' => 20],
                ['key' => 'stun', 'name' => 'Ogłuszenie', 'suffix' => '%', 'min' => 1, 'max' => 3],
            ],
            ItemType::Armor => [
                ['key' => 'hp', 'name' => 'Punkty życia', 'suffix' => '', 'min' => 5, 'max' => 20],
                ['key' => 'dodge', 'name' => 'Unik', 'suffix' => '%', 'min' => 1, 'max' => 4],
            ],
            ItemType::Talisman => [
                ['key' => 'hp', 'name' => 'Punkty życia', 'suffix' => '', 'min' => 5, 'max' => 25],
                ['key' => 'critChance', 'name' => 'Szansa krytyka', 'suffix' => '%', 'min' => 1, 'max' => 6],
                ['key' => 'critPower', 'name' => 'Moc krytyka', 'suffix' => '%', 'min' => 5, 'max' => 25],
                ['key' => 'dodge', 'name' => 'Unik', 'suffix' => '%', 'min' => 1, 'max' => 5],
                ['key' => 'stun', 'name' => 'Ogłuszenie', 'suffix' => '%', 'min' => 1, 'max' => 4],
            ],
            ItemType::Potion => [],
        };

        shuffle($possible);
        $selected = array_slice($possible, 0, min($count, count($possible)));
        $bonusStats = [];

        foreach ($selected as $stat) {
            $value = (int) floor(random_int($stat['min'], $stat['max']) * (1 + $level * 0.1) * $rarityMultiplier);
            $bonusStats[$stat['key']] = [
                'value' => max(1, $value),
                'name' => $stat['name'],
                'suffix' => $stat['suffix'],
            ];
        }

        return $bonusStats;
    }

    /**
     * @param  array<string, array{value: int, name: string, suffix: string}>  $bonusStats
     * @return array<string, int>
     */
    private function flattenBonusStats(array $bonusStats): array
    {
        return array_map(
            fn (array $stat): int => $stat['value'],
            $bonusStats,
        );
    }

    /**
     * @param  array<string, int|float>  $stats
     * @param  array<string, mixed>|null  $effect
     */
    private function power(ItemType $type, array $stats, ?array $effect, float $rarityMultiplier): int
    {
        return match ($type) {
            ItemType::Weapon => (int) floor((($stats['dmgMin'] ?? 0) + ($stats['dmgMax'] ?? 0)) * 5 * $rarityMultiplier),
            ItemType::Armor => (int) floor(($stats['armor'] ?? 1) * 8 * $rarityMultiplier),
            ItemType::Talisman => (int) floor(max(1, array_sum($stats)) * 3 * $rarityMultiplier),
            ItemType::Potion => (int) (($effect['value'] ?? 1) * 2),
        };
    }

    private function percentRoll(): float
    {
        return random_int(1, 10_000) / 100;
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    private function random(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    /**
     * @template T of \UnitEnum
     *
     * @param  array<int, T>  $cases
     * @return T
     */
    private function randomCase(array $cases): mixed
    {
        return $this->random($cases);
    }
}
