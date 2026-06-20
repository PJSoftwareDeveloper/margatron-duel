<?php

namespace Tests\Feature;

use App\Game\Services\GameProfileService;
use App\Models\GameProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AchievementTest extends TestCase
{
    use RefreshDatabase;

    public function test_achievements_screen_requires_authentication(): void
    {
        $this->get('/achievements')->assertRedirect('/login');
    }

    public function test_achievements_screen_returns_profile_progress(): void
    {
        $now = Carbon::parse('2026-06-20 15:00:00');
        Carbon::setTestNow($now);

        try {
            $user = User::factory()->create();
            GameProfile::factory()->for($user)->create([
                'level' => 20,
                'played_seconds' => 17_940,
                'last_seen_at' => $now->copy()->subSeconds(60),
                'strength' => 18,
                'vitality_points_assigned' => 10,
                'strength_points_assigned' => 4,
                'luck_points_assigned' => 5,
                'monsters_killed' => 125,
                'unique_items_found' => 10,
                'heroic_items_found' => 2,
                'legendary_items_found' => 1,
                'equipped' => [
                    'weapon' => null,
                    'armor' => [
                        'name' => 'Testowy pancerz',
                        'type' => 'armor',
                        'stats' => ['armor' => 7],
                        'armor' => 7,
                    ],
                    'accessory' => [
                        'name' => 'Testowy amulet',
                        'type' => 'talisman',
                        'stats' => ['stun' => 32],
                        'stun' => 32,
                    ],
                ],
            ]);

            $this->actingAs($user)
                ->get('/achievements')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Game/Achievements')
                    ->has('game.data.user')
                    ->has('achievements.entries', 12)
                    ->where('achievements.entries.0.id', 'reach_level_20')
                    ->where('achievements.entries.0.completed', true)
                    ->where('achievements.entries.1.id', 'play_five_hours')
                    ->where('achievements.entries.1.completed', true)
                    ->where('achievements.entries.3.id', 'assign_strength_10')
                    ->where('achievements.entries.3.percent', 40)
                    ->where('achievements.entries.5.id', 'reach_damage_20')
                    ->where('achievements.entries.5.completed', true)
                    ->where('achievements.completedCount', 6)
                    ->where('achievements.overallPercent', 64)
                );

            $this->assertSame(18_000, GameProfile::query()->whereBelongsTo($user)->value('played_seconds'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_assigning_attribute_increments_lifetime_counter(): void
    {
        $user = User::factory()->create();
        GameProfile::factory()->for($user)->create([
            'attribute_points' => 1,
            'vitality_points_assigned' => 0,
        ]);

        $this->actingAs($user)
            ->postJson('/game/actions/attribute', ['attribute' => 'vitality'])
            ->assertOk();

        $profile = GameProfile::query()->whereBelongsTo($user)->firstOrFail();

        $this->assertSame(1, $profile->vitality_points_assigned);
        $this->assertSame(6, $profile->vitality);
    }

    public function test_play_time_tracking_is_capped_between_requests(): void
    {
        $now = Carbon::parse('2026-06-20 16:00:00');
        Carbon::setTestNow($now);

        try {
            $profile = GameProfile::factory()->create([
                'played_seconds' => 30,
                'last_seen_at' => $now->copy()->subHour(),
            ]);

            app(GameProfileService::class)->recordActivity($profile);

            $this->assertSame(330, $profile->refresh()->played_seconds);
        } finally {
            Carbon::setTestNow();
        }
    }
}
