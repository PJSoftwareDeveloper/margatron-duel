<?php

namespace App\Models;

use Database\Factories\GameProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'level',
    'exp',
    'exp_max',
    'gold',
    'pa',
    'pa_max',
    'vitality',
    'strength',
    'luck',
    'attribute_points',
    'hp',
    'hp_max',
    'dmg_min',
    'dmg_max',
    'armor',
    'crit_chance',
    'crit_power',
    'dodge',
    'stun',
    'current_map_id',
    'stage_progress',
    'inventory',
    'equipped',
])]
class GameProfile extends Model
{
    /** @use HasFactory<GameProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'crit_chance' => 'float',
            'crit_power' => 'float',
            'dodge' => 'float',
            'stun' => 'float',
            'stage_progress' => 'array',
            'inventory' => 'array',
            'equipped' => 'array',
        ];
    }
}
