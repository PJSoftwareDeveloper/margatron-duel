<?php

namespace App\Game\Repositories;

use App\Game\Enums\ArenaDifficulty;
use App\Game\Enums\GameMap;
use App\Game\Enums\ItemRarity;
use App\Game\Enums\ItemType;
use App\Game\Enums\LocationType;
use InvalidArgumentException;

final readonly class StaticGameCatalogRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function maps(): array
    {
        return [
            GameMap::Ithan->value => $this->buildMap(GameMap::Ithan, [
                'locations' => [
                    $this->battle('ithan-yss', 'Dolina Yss(6-10lvl)', 90, 7, 3, 6, 10, ['wolf', 'spider']),
                    $this->battle('ithan-hunters-cave', 'Jaskinia Łowców(1-5lvl)', 46, 13, 1, 1, 5, ['goblin', 'rat']),
                    $this->buildLocation('ithan-arena', 'Arena', LocationType::Arena, 23.3, 27, 2),
                    $this->shop('ithan-roan', 'Sklep Roana', 87.5, 70, 'blacksmith_1'),
                    $this->buildLocation('ithan-inn', 'Karczma pod Rozbrykanym Niziołkiem', LocationType::Rest, 33.5, 76),
                    $this->shop('ithan-makatara', 'Sklep Makatary', 8.6, 76, 'alchemist_1'),
                    $this->buildLocation('ithan-world', 'Mapa Świata', LocationType::WorldMap, 46, 93),
                ],
                'enemies' => [
                    'goblin' => $this->enemy('Gaunt', 'gaunt.gif', 15, 1, 3, 6, 0),
                    'rat' => $this->enemy('Szczur', 'szczur.gif', 8, 1, 2, 4, 0),
                    'wolf' => $this->enemy('Wilk', 'wolf.gif', 25, 3, 6, 11, 0),
                    'spider' => $this->enemy('Pająk', 'spider.gif', 30, 4, 7, 14, 0),
                ],
                'arenaEnemies' => [
                    ArenaDifficulty::Easy->value => ['goblin', 'rat'],
                    ArenaDifficulty::Medium->value => ['wolf', 'spider'],
                    ArenaDifficulty::Hard->value => ['spider', 'wolf'],
                ],
            ]),
            GameMap::Torneg->value => $this->buildMap(GameMap::Torneg, [
                'locations' => [
                    $this->battle('torneg-mountain-cave', 'Górska Grota(11-15lvl)', 30.7, 7, 9, 11, 15, ['dark_wolf', 'pelzacz'], 2),
                    $this->battle('torneg-spider-nest', 'Gniazdo Pająków(16-20lvl)', 95, 70, 12, 16, 20, ['giant_spider', 'spider_queen'], 2),
                    $this->buildLocation('torneg-arena', 'Arena', LocationType::Arena, 60.5, 14, 3, ['levelReq' => 9]),
                    $this->shop('torneg-syntia', 'Syntia', 75, 39, 'blacksmith_2'),
                    $this->buildLocation('torneg-inn', 'Karczma Umbara', LocationType::Rest, 82, 93),
                    $this->shop('torneg-salome', 'Salome', 5, 53, 'alchemist_2'),
                    $this->buildLocation('torneg-world', 'Mapa Świata', LocationType::WorldMap, 53, 93),
                ],
                'enemies' => [
                    'dark_wolf' => $this->enemy('Mroczny Wilk', 'dark_wolf.gif', 55, 8, 14, 28, 4),
                    'pelzacz' => $this->enemy('Pełzacz', 'pelzacz.gif', 65, 9, 16, 32, 5),
                    'giant_spider' => $this->enemy('Olbrzymi Pająk', 'giant_spider.gif', 85, 13, 22, 48, 8),
                    'spider_queen' => $this->enemy('Królowa Pająków', 'spider_queen.gif', 110, 16, 27, 65, 12),
                ],
                'arenaEnemies' => [
                    ArenaDifficulty::Easy->value => ['dark_wolf', 'pelzacz'],
                    ArenaDifficulty::Medium->value => ['giant_spider', 'dark_wolf'],
                    ArenaDifficulty::Hard->value => ['spider_queen', 'giant_spider'],
                ],
            ]),
            GameMap::KarkaHan->value => $this->buildMap(GameMap::KarkaHan, [
                'locations' => [
                    $this->battle('karka-virgin-forest', 'Dziewicza Knieja(21-25lvl)', 31, 25, 20, 21, 25, ['zubr', 'grzechotnik'], 2),
                    $this->battle('karka-zulu-settlement', 'Osada Zulusów(26-30lvl)', 5, 56, 24, 26, 30, ['giant_spider', 'spider_queen'], 2),
                    $this->buildLocation('karka-arena', 'Arena', LocationType::Arena, 73.2, 20, 3, ['levelReq' => 20]),
                    $this->shop('karka-armorer', 'Płatnerz', 80, 58, 'blacksmith_2'),
                    $this->buildLocation('karka-inn', 'Karczma', LocationType::Rest, 95, 7),
                    $this->shop('karka-craftsman', 'Rzemieślnik', 14, 33, 'alchemist_2'),
                    $this->buildLocation('karka-world', 'Mapa Świata', LocationType::WorldMap, 44, 93),
                ],
                'enemies' => [
                    'zubr' => $this->enemy('Żubr', 'zubr.gif', 135, 21, 34, 92, 18),
                    'grzechotnik' => $this->enemy('Grzechotnik', 'grzechotnik.gif', 105, 24, 39, 96, 20),
                    'giant_spider' => $this->enemy('Olbrzymi Pająk', 'giant_spider.gif', 150, 28, 44, 118, 26),
                    'spider_queen' => $this->enemy('Królowa Pająków', 'spider_queen.gif', 185, 34, 52, 150, 35),
                ],
                'arenaEnemies' => [
                    ArenaDifficulty::Easy->value => ['zubr', 'grzechotnik'],
                    ArenaDifficulty::Medium->value => ['giant_spider', 'zubr'],
                    ArenaDifficulty::Hard->value => ['spider_queen', 'giant_spider'],
                ],
            ]),
            GameMap::Werbin->value => $this->buildMap(GameMap::Werbin, [
                'locations' => [
                    $this->battle('werbin-heaths', 'Wrzosowiska(31-35lvl)', 84, 7, 30, 31, 35, ['zubr', 'grzechotnik'], 2),
                    $this->battle('werbin-goblin-forest', 'Las Goblinów(36-40lvl)', 95, 67, 35, 36, 40, ['giant_spider', 'spider_queen'], 2),
                    $this->buildLocation('werbin-arena', 'Arena', LocationType::Arena, 29.2, 20, 3, ['levelReq' => 35]),
                    $this->shop('werbin-armorer', 'Płatnerz', 39, 58, 'blacksmith_3'),
                    $this->buildLocation('werbin-inn', 'Karczma', LocationType::Rest, 70, 58),
                    $this->shop('werbin-craftsman', 'Rzemieślnik', 8.4, 7, 'alchemist_3'),
                    $this->buildLocation('werbin-world', 'Mapa Świata', LocationType::WorldMap, 44, 93),
                ],
                'enemies' => [
                    'zubr' => $this->enemy('Żubr', 'zubr.gif', 220, 42, 66, 210, 48),
                    'grzechotnik' => $this->enemy('Grzechotnik', 'grzechotnik.gif', 180, 48, 75, 225, 52),
                    'giant_spider' => $this->enemy('Olbrzymi Pająk', 'giant_spider.gif', 260, 55, 88, 280, 70),
                    'spider_queen' => $this->enemy('Królowa Pająków', 'spider_queen.gif', 330, 68, 105, 360, 90),
                ],
                'arenaEnemies' => [
                    ArenaDifficulty::Easy->value => ['zubr', 'grzechotnik'],
                    ArenaDifficulty::Medium->value => ['giant_spider', 'zubr'],
                    ArenaDifficulty::Hard->value => ['spider_queen', 'giant_spider'],
                ],
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function worldMapPositions(): array
    {
        return [
            ['id' => 1, 'x' => 42, 'y' => 49],
            ['id' => 2, 'x' => 45, 'y' => 39],
            ['id' => 3, 'x' => 56, 'y' => 48],
            ['id' => 4, 'x' => 40, 'y' => 32],
            ['id' => 5, 'x' => 51, 'y' => 34],
            ['id' => 6, 'x' => 61, 'y' => 41],
            ['id' => 7, 'x' => 34, 'y' => 43],
            ['id' => 8, 'x' => 49, 'y' => 58],
            ['id' => 9, 'x' => 58, 'y' => 61],
            ['id' => 10, 'x' => 66, 'y' => 53],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function shops(): array
    {
        return [
            'blacksmith_1' => [
                'id' => 'blacksmith_1',
                'name' => 'Sklep Roana',
                'items' => [
                    $this->shopItem(201, 'Miecz żelazny', 'items/sword.gif', ItemType::Weapon, ItemRarity::Common, 1, ['dmgMin' => 3, 'dmgMax' => 7], 150),
                    $this->shopItem(202, 'Topór wojenny', 'items/axe.gif', ItemType::Weapon, ItemRarity::Common, 3, ['dmgMin' => 5, 'dmgMax' => 10], 300),
                    $this->shopItem(203, 'Wzmocniony Sztylet', 'items/dagger.gif', ItemType::Weapon, ItemRarity::Unique, 5, ['dmgMin' => 4, 'dmgMax' => 8, 'critChance' => 3], 450),
                    $this->shopItem(211, 'Skórzana zbroja', 'items/leather.gif', ItemType::Armor, ItemRarity::Common, 1, ['armor' => 5], 100),
                    $this->shopItem(212, 'Kolczuga', 'items/chainmail.gif', ItemType::Armor, ItemRarity::Common, 4, ['armor' => 12], 250),
                    $this->shopItem(213, 'Błogosławiona Peleryna', 'items/cloak.gif', ItemType::Armor, ItemRarity::Unique, 5, ['armor' => 8, 'dodge' => 2], 400),
                    $this->shopItem(221, 'Pierścień Wojownika', 'items/ring.gif', ItemType::Talisman, ItemRarity::Common, 2, ['hp' => 10], 120),
                    $this->shopItem(222, 'Mistyczny Amulet', 'items/amulet.gif', ItemType::Talisman, ItemRarity::Unique, 6, ['critChance' => 2, 'hp' => 15], 500),
                ],
            ],
            'alchemist_1' => [
                'id' => 'alchemist_1',
                'name' => 'Sklep Makatary',
                'items' => [
                    $this->shopItem(301, 'Mała mikstura życia', 'items/health.gif', ItemType::Potion, ItemRarity::Common, 1, [], 0, ['type' => 'heal', 'value' => 20]),
                    $this->shopItem(302, 'Średnia mikstura życia', 'items/health.gif', ItemType::Potion, ItemRarity::Common, 3, [], 80, ['type' => 'heal', 'value' => 50]),
                    $this->shopItem(303, 'Duża mikstura życia', 'items/health.gif', ItemType::Potion, ItemRarity::Common, 5, [], 150, ['type' => 'heal', 'value' => 100]),
                    $this->shopItem(311, 'Mała butelka PA', 'items/pa.gif', ItemType::Potion, ItemRarity::Common, 1, [], 0, ['type' => 'pa', 'value' => 5]),
                    $this->shopItem(312, 'Średnia butelka PA', 'items/pa.gif', ItemType::Potion, ItemRarity::Common, 5, [], 180, ['type' => 'pa', 'value' => 10]),
                    $this->shopItem(321, 'Eliksir Siły', 'items/strength.gif', ItemType::Potion, ItemRarity::Unique, 4, ['strength' => 3], 200, ['type' => 'buff_strength', 'value' => 3]),
                ],
            ],
            'blacksmith_2' => [
                'id' => 'blacksmith_2',
                'name' => 'Płatnerz',
                'items' => [
                    $this->shopItem(401, 'Mroczny Miecz', 'items/sword.gif', ItemType::Weapon, ItemRarity::Common, 10, ['dmgMin' => 12, 'dmgMax' => 20], 800),
                    $this->shopItem(402, 'Topór Cienia', 'items/axe.gif', ItemType::Weapon, ItemRarity::Common, 12, ['dmgMin' => 15, 'dmgMax' => 25], 1200),
                    $this->shopItem(403, 'Bohaterski Młot', 'items/hammer.gif', ItemType::Weapon, ItemRarity::Heroic, 15, ['dmgMin' => 18, 'dmgMax' => 30, 'critChance' => 5, 'critPower' => 15], 2500),
                    $this->shopItem(404, 'Legendarny Miecz Zagłady', 'items/sword.gif', ItemType::Weapon, ItemRarity::Legendary, 18, ['dmgMin' => 25, 'dmgMax' => 40, 'critChance' => 8, 'critPower' => 25, 'doubleDamage' => 5], 8000),
                    $this->shopItem(411, 'Zbroja Cieni', 'items/plate.gif', ItemType::Armor, ItemRarity::Common, 10, ['armor' => 25], 900),
                    $this->shopItem(412, 'Epicki Pancerz Strażnika', 'items/plate.gif', ItemType::Armor, ItemRarity::Heroic, 15, ['armor' => 35, 'hp' => 30, 'dodge' => 3], 3000),
                    $this->shopItem(413, 'Nieśmiertelna Zbroja', 'items/plate.gif', ItemType::Armor, ItemRarity::Legendary, 18, ['armor' => 50, 'hp' => 50, 'dodge' => 5, 'doubleArmor' => 5], 10000),
                    $this->shopItem(421, 'Potężny Pierścień', 'items/ring.gif', ItemType::Talisman, ItemRarity::Heroic, 14, ['critChance' => 4, 'critPower' => 12], 1500),
                    $this->shopItem(422, 'Mityczny Amulet Mocy', 'items/amulet.gif', ItemType::Talisman, ItemRarity::Legendary, 18, ['hp' => 40, 'critChance' => 6, 'stun' => 3], 5000),
                ],
            ],
            'alchemist_2' => [
                'id' => 'alchemist_2',
                'name' => 'Rzemieślnik',
                'items' => [
                    $this->shopItem(501, 'Wielka mikstura życia', 'items/health.gif', ItemType::Potion, ItemRarity::Common, 10, [], 0, ['type' => 'heal', 'value' => 150]),
                    $this->shopItem(502, 'Epicka mikstura życia', 'items/health.gif', ItemType::Potion, ItemRarity::Heroic, 10, [], 400, ['type' => 'heal', 'value' => 300]),
                    $this->shopItem(511, 'Duża butelka PA', 'items/pa.gif', ItemType::Potion, ItemRarity::Common, 10, [], 250, ['type' => 'pa', 'value' => 15]),
                    $this->shopItem(521, 'Potężny Eliksir Siły', 'items/strength.gif', ItemType::Potion, ItemRarity::Heroic, 12, ['strength' => 8], 400, ['type' => 'buff_strength', 'value' => 8]),
                    $this->shopItem(522, 'Eliksir Krytyka', 'items/crit.gif', ItemType::Potion, ItemRarity::Unique, 10, ['critChance' => 15], 300, ['type' => 'buff_crit', 'value' => 15]),
                    $this->shopItem(523, 'Legendarny Eliksir Mocy', 'items/crit.gif', ItemType::Potion, ItemRarity::Legendary, 15, [], 1000, ['type' => 'buff_all', 'value' => 10]),
                    $this->shopItem(531, 'Mikstura Ochrony', 'items/strength.gif', ItemType::Potion, ItemRarity::Unique, 10, ['armor' => 15], 250, ['type' => 'buff_armor', 'value' => 15]),
                ],
            ],
            'blacksmith_3' => [
                'id' => 'blacksmith_3',
                'name' => 'Płatnerz',
                'items' => [
                    $this->shopItem(601, 'Smocza Kosa', 'items/spear.gif', ItemType::Weapon, ItemRarity::Heroic, 20, ['dmgMin' => 30, 'dmgMax' => 50, 'critChance' => 7, 'critPower' => 20], 5000),
                    $this->shopItem(602, 'Boski Miecz Zagłady', 'items/sword.gif', ItemType::Weapon, ItemRarity::Legendary, 25, ['dmgMin' => 45, 'dmgMax' => 70, 'critChance' => 12, 'critPower' => 35, 'doubleDamage' => 8], 15000),
                    $this->shopItem(611, 'Smocza Łuska', 'items/plate.gif', ItemType::Armor, ItemRarity::Legendary, 22, ['armor' => 70, 'hp' => 80, 'dodge' => 7, 'doubleArmor' => 8], 18000),
                ],
            ],
            'alchemist_3' => [
                'id' => 'alchemist_3',
                'name' => 'Rzemieślnik',
                'items' => [
                    $this->shopItem(701, 'Smoczy Eliksir Życia', 'items/health.gif', ItemType::Potion, ItemRarity::Legendary, 25, [], 800, ['type' => 'heal', 'value' => 500]),
                    $this->shopItem(702, 'Krew Smoka', 'items/strength.gif', ItemType::Potion, ItemRarity::Legendary, 30, [], 2000, ['type' => 'buff_all', 'value' => 20]),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function itemBases(): array
    {
        return [
            ItemType::Weapon->value => [
                ['name' => 'Miecz', 'image' => 'items/sword.gif', 'dmgMin' => 2, 'dmgMax' => 5],
                ['name' => 'Topór', 'image' => 'items/axe.gif', 'dmgMin' => 3, 'dmgMax' => 7],
                ['name' => 'Sztylet', 'image' => 'items/dagger.gif', 'dmgMin' => 1, 'dmgMax' => 4],
                ['name' => 'Młot', 'image' => 'items/hammer.gif', 'dmgMin' => 4, 'dmgMax' => 8],
                ['name' => 'Włócznia', 'image' => 'items/spear.gif', 'dmgMin' => 2, 'dmgMax' => 6],
            ],
            ItemType::Armor->value => [
                ['name' => 'Skórzana zbroja', 'image' => 'items/leather.gif', 'armor' => 3],
                ['name' => 'Kolczuga', 'image' => 'items/chainmail.gif', 'armor' => 6],
                ['name' => 'Zbroja płytowa', 'image' => 'items/plate.gif', 'armor' => 10],
                ['name' => 'Szata', 'image' => 'items/robe.gif', 'armor' => 4],
                ['name' => 'Peleryna', 'image' => 'items/cloak.gif', 'armor' => 2],
            ],
            ItemType::Talisman->value => [
                ['name' => 'Pierścień', 'image' => 'items/ring.gif'],
                ['name' => 'Amulet', 'image' => 'items/amulet.gif'],
                ['name' => 'Talizman', 'image' => 'items/charm.gif'],
                ['name' => 'Medal', 'image' => 'items/medal.gif'],
                ['name' => 'Runa', 'image' => 'items/rune.gif'],
            ],
            ItemType::Potion->value => [
                ['name' => 'Mikstura życia', 'image' => 'items/health.gif', 'effect' => ['type' => 'heal', 'value' => 25]],
                ['name' => 'Butelka PA', 'image' => 'items/pa.gif', 'effect' => ['type' => 'pa', 'value' => 5]],
                ['name' => 'Eliksir siły', 'image' => 'items/strength.gif', 'effect' => ['type' => 'buff_strength', 'value' => 2]],
                ['name' => 'Eliksir krytyka', 'image' => 'items/crit.gif', 'effect' => ['type' => 'buff_crit', 'value' => 5]],
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rarityPrefixes(): array
    {
        return [
            ItemRarity::Unique->value => ['Mocny', 'Wzmocniony', 'Zaklęty', 'Mistyczny'],
            ItemRarity::Heroic->value => ['Bohaterski', 'Epicki', 'Potężny', 'Starożytny'],
            ItemRarity::Legendary->value => ['Legendarny', 'Mityczny', 'Boski', 'Nieśmiertelny'],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function baseDropChances(): array
    {
        return [
            ItemRarity::Common->value => 60,
            ItemRarity::Unique->value => 25,
            ItemRarity::Heroic->value => 12,
            ItemRarity::Legendary->value => 3,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function map(int|GameMap $map): array
    {
        $mapId = $map instanceof GameMap ? $map->value : $map;

        return $this->maps()[$mapId] ?? throw new InvalidArgumentException("Unknown map [{$mapId}].");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function shopById(string $shopId): ?array
    {
        return $this->shops()[$shopId] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function location(int $mapId, string $locationId): ?array
    {
        foreach ($this->map($mapId)['locations'] as $location) {
            if ($location['id'] === $locationId) {
                return $location;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function scaledEnemy(int $mapId, string $enemyKey, int $level): array
    {
        $enemy = $this->map($mapId)['enemies'][$enemyKey] ?? throw new InvalidArgumentException("Unknown enemy [{$enemyKey}].");
        $multiplier = 1 + (($level - 1) * 0.15);

        return [
            ...$enemy,
            'key' => $enemyKey,
            'level' => $level,
            'hp' => (int) floor($enemy['baseHp'] * $multiplier),
            'dmgMin' => (int) floor($enemy['dmgMin'] * $multiplier),
            'dmgMax' => (int) floor($enemy['dmgMax'] * $multiplier),
            'exp' => (int) floor($enemy['exp'] * $multiplier),
            'gold' => (int) floor($enemy['gold'] * $multiplier),
        ];
    }

    /**
     * @param  array<string, mixed>  $location
     * @return array<int, array<string, mixed>>
     */
    public function stagesForLocation(array $location, int $unlockedStage): array
    {
        $stages = [];
        $levelMin = (int) ($location['levelMin'] ?? 1);
        $levelMax = (int) ($location['levelMax'] ?? 5);

        for ($stage = 1; $stage <= 5; $stage++) {
            $level = (int) floor($levelMin + (($levelMax - $levelMin) * ($stage - 1) / 4));
            $stages[] = [
                'stage' => $stage,
                'level' => $level,
                'unlocked' => $stage <= $unlockedStage,
                'completed' => $stage < $unlockedStage,
            ];
        }

        return $stages;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildMap(GameMap $map, array $data): array
    {
        $meta = $map->meta();

        return [
            'id' => $map->value,
            'name' => $meta->name,
            'image' => $meta->image,
            'imageUrl' => $this->assetUrl($meta->image),
            'requiredLevel' => $meta->requiredLevel,
            'levelRange' => [
                'min' => $meta->levelMin,
                'max' => $meta->levelMax,
            ],
            ...$data,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function buildLocation(string $id, string $name, LocationType $type, float $x, float $y, int $pa = 1, array $extra = []): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'type' => $type->value,
            'x' => $x,
            'y' => $y,
            'pa' => $pa,
            ...$extra,
        ];
    }

    /**
     * @param  array<int, string>  $enemies
     * @return array<string, mixed>
     */
    private function battle(string $id, string $name, float $x, float $y, int $levelReq, int $levelMin, int $levelMax, array $enemies, int $pa = 1): array
    {
        return $this->buildLocation($id, $name, LocationType::Battle, $x, $y, $pa, [
            'levelReq' => $levelReq,
            'levelMin' => $levelMin,
            'levelMax' => $levelMax,
            'enemies' => $enemies,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shop(string $id, string $name, float $x, float $y, string $shopId): array
    {
        return $this->buildLocation($id, $name, LocationType::Shop, $x, $y, 1, [
            'shopId' => $shopId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function enemy(string $name, string $image, int $baseHp, int $dmgMin, int $dmgMax, int $exp, int $gold): array
    {
        return [
            'name' => $name,
            'image' => $image,
            'imageUrl' => $this->assetUrl("monsters/{$image}"),
            'baseHp' => $baseHp,
            'dmgMin' => $dmgMin,
            'dmgMax' => $dmgMax,
            'exp' => $exp,
            'gold' => $gold,
        ];
    }

    /**
     * @param  array<string, int|float>  $stats
     * @param  array<string, mixed>|null  $effect
     * @return array<string, mixed>
     */
    private function shopItem(int $id, string $name, string $image, ItemType $type, ItemRarity $rarity, int $level, array $stats, int $price, ?array $effect = null): array
    {
        $meta = $rarity->meta();
        $power = (int) max(1, array_sum(array_map(fn (int|float $value): int|float => $value, $stats)));

        return [
            'id' => $id,
            'name' => $name,
            'icon' => $image,
            'image' => $image,
            'imageUrl' => $this->assetUrl($image),
            'type' => $type->value,
            'itemType' => $type->value,
            'itemTypeName' => $type->label(),
            'rarity' => $rarity->value,
            'rarityName' => $meta->label,
            'rarityColor' => $meta->color,
            'rarityCss' => $meta->cssClass,
            'level' => $level,
            'stats' => $stats,
            'bonusStats' => $stats,
            'effect' => $effect['type'] ?? null,
            'effectValue' => $effect['value'] ?? null,
            'effectData' => $effect,
            'power' => $power,
            'price' => $price,
            'quantity' => 1,
            ...$stats,
        ];
    }

    private function assetUrl(string $path): string
    {
        return "/game-assets/{$path}";
    }
}
