<?php

namespace App\Http\Resources;

use App\Game\Services\GameProfileService;
use App\Models\GameProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameProfile
 */
final class GameProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paRegenerationSeconds = GameProfileService::actionPointRegenerationSeconds();
        $paRegenerationLimit = GameProfileService::actionPointRegenerationLimit();
        $paRegeneratesAt = $this->pa < $paRegenerationLimit && $this->pa_regenerated_at
            ? $this->pa_regenerated_at->copy()->addSeconds($paRegenerationSeconds)->toIso8601String()
            : null;

        return [
            'id' => $this->user_id,
            'profileId' => $this->id,
            'nick' => $this->user?->name,
            'email' => $this->user?->email,
            'level' => $this->level,
            'exp' => $this->exp,
            'expMax' => $this->exp_max,
            'gold' => $this->gold,
            'pa' => $this->pa,
            'paMax' => $this->pa_max,
            'paLimit' => $paRegenerationLimit,
            'paRegenerationLimit' => $paRegenerationLimit,
            'paRegenerationSeconds' => $paRegenerationSeconds,
            'paRegeneratesAt' => $paRegeneratesAt,
            'vitality' => $this->vitality,
            'strength' => $this->strength,
            'luck' => $this->luck,
            'attributePoints' => $this->attribute_points,
            'hp' => $this->hp,
            'hpMax' => $this->hp_max,
            'dmgMin' => $this->dmg_min,
            'dmgMax' => $this->dmg_max,
            'armor' => $this->armor,
            'critChance' => $this->crit_chance,
            'critPower' => $this->crit_power,
            'dodge' => $this->dodge,
            'stun' => $this->stun,
            'currentMapId' => $this->current_map_id,
            'inventory' => $this->inventory,
            'equipped' => $this->equipped,
        ];
    }
}
