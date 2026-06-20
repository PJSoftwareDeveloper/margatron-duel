<?php

namespace Database\Factories;

use App\Game\Services\GameProfileService;
use App\Models\GameProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameProfile>
 */
final class GameProfileFactory extends Factory
{
    protected $model = GameProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            ...GameProfileService::defaults(),
        ];
    }
}
