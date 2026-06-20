<?php

namespace App\Game\Services;

use App\Game\Enums\PlayerAttribute;
use App\Models\GameProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class GameProfileService
{
    public const int INVENTORY_SIZE = 15;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'level' => 1,
            'exp' => 0,
            'exp_max' => self::expForNextLevel(1),
            'gold' => 100,
            'pa' => 20,
            'pa_max' => 20,
            'vitality' => 5,
            'strength' => 5,
            'luck' => 5,
            'attribute_points' => 0,
            'hp' => 50,
            'hp_max' => 50,
            'dmg_min' => 1,
            'dmg_max' => 2,
            'armor' => 0,
            'crit_chance' => 5,
            'crit_power' => 150,
            'dodge' => 3,
            'stun' => 0,
            'current_map_id' => 1,
            'stage_progress' => [],
            'inventory' => array_fill(0, self::INVENTORY_SIZE, null),
            'equipped' => [
                'weapon' => null,
                'armor' => null,
                'accessory' => null,
            ],
        ];
    }

    public static function expForNextLevel(int $level): int
    {
        return (int) floor(5 + ($level * $level * 15));
    }

    public function ensureFor(User $user): GameProfile
    {
        /** @var GameProfile $profile */
        $profile = $user->gameProfile()->firstOrCreate(
            ['user_id' => $user->id],
            self::defaults(),
        );

        return $this->recalculate($profile);
    }

    public function recalculate(GameProfile $profile): GameProfile
    {
        $equipped = $profile->equipped ?? [];
        $weapon = $equipped['weapon'] ?? null;
        $armor = $equipped['armor'] ?? null;
        $accessory = $equipped['accessory'] ?? null;

        $baseDmgMin = (int) ($this->stat($weapon, 'dmgMin') ?: 1);
        $baseDmgMax = (int) ($this->stat($weapon, 'dmgMax') ?: 2);
        $baseArmor = (int) ($this->stat($armor, 'armor') ?: 0);
        $hpBonus = (int) ($this->stat($weapon, 'hp') + $this->stat($armor, 'hp') + $this->stat($accessory, 'hp'));
        $critChanceBonus = $this->stat($weapon, 'critChance') + $this->stat($accessory, 'critChance');
        $critPowerBonus = $this->stat($weapon, 'critPower') + $this->stat($accessory, 'critPower');
        $dodgeBonus = $this->stat($armor, 'dodge') + $this->stat($accessory, 'dodge');
        $stunBonus = $this->stat($weapon, 'stun') + $this->stat($accessory, 'stun');
        $newHpMax = 50 + (($profile->vitality - 5) * 10) + (($profile->level - 1) * 5) + $hpBonus;

        $profile->forceFill([
            'hp_max' => max(1, $newHpMax),
            'hp' => min($profile->hp, max(1, $newHpMax)),
            'dmg_min' => $baseDmgMin + (int) floor($profile->strength / 2),
            'dmg_max' => $baseDmgMax + $profile->strength,
            'armor' => $baseArmor,
            'crit_chance' => 5 + $critChanceBonus + (int) floor($profile->luck / 3),
            'crit_power' => 150 + $critPowerBonus,
            'dodge' => 2 + $dodgeBonus + (int) floor($profile->luck / 5),
            'stun' => $stunBonus,
        ])->save();

        return $profile->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function addExperience(GameProfile $profile, int $amount): array
    {
        $startLevel = $profile->level;
        $currentExp = $profile->exp + max(0, $amount);
        $levelsGained = 0;

        while ($currentExp >= $profile->exp_max) {
            $currentExp -= $profile->exp_max;
            $profile->level++;
            $levelsGained++;
            $profile->exp_max = self::expForNextLevel($profile->level);
            $profile->attribute_points += 2;
            $profile->pa_max += 5;
            $profile->pa = $profile->pa_max;
        }

        $profile->exp = $currentExp;

        if ($levelsGained > 0) {
            $profile->hp = 999_999;
        }

        $profile->save();
        $this->recalculate($profile);

        return [
            'leveledUp' => $levelsGained > 0,
            'levelsGained' => $levelsGained,
            'oldLevel' => $startLevel,
            'newLevel' => $profile->level,
            'newPaMax' => $profile->pa_max,
        ];
    }

    public function addAttribute(GameProfile $profile, PlayerAttribute $attribute): GameProfile
    {
        if ($profile->attribute_points <= 0) {
            return $profile;
        }

        DB::transaction(function () use ($profile, $attribute): void {
            $profile->attribute_points--;
            $profile->{$attribute->value}++;

            if ($attribute === PlayerAttribute::Vitality) {
                $profile->hp += 10;
            }

            $profile->save();
            $this->recalculate($profile);
        });

        return $profile->refresh();
    }

    /**
     * @param  array<string, mixed>|null  $item
     */
    private function stat(?array $item, string $key): int|float
    {
        if (! $item) {
            return 0;
        }

        foreach ([$item, $item['stats'] ?? [], $item['bonusStats'] ?? []] as $source) {
            if (! is_array($source) || ! array_key_exists($key, $source)) {
                continue;
            }

            $value = $source[$key];

            if (is_array($value)) {
                return $value['value'] ?? 0;
            }

            return is_numeric($value) ? $value : 0;
        }

        return 0;
    }
}
