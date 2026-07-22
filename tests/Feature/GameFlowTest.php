<?php

namespace Tests\Feature;

use App\Events\ActionPointsChanged;
use App\Game\Services\ActionPointRegenerationScheduler;
use App\Game\Services\GameProfileService;
use App\Game\Services\TavernRestService;
use App\Jobs\CompleteRest;
use App\Jobs\RegenerateActionPoints;
use App\Models\GameProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class GameFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_user_profile_and_starts_session(): void
    {
        $response = $this->post('/register', [
            'nick' => 'SeniorNick',
            'email' => 'senior@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('game.show'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'senior@example.com',
            'name' => 'SeniorNick',
        ]);
        $this->assertDatabaseHas('game_profiles', [
            'level' => 1,
            'gold' => 100,
            'pa' => 20,
        ]);
    }

    public function test_game_screen_requires_authentication(): void
    {
        $this->get('/game')->assertRedirect('/login');
    }

    public function test_guest_auth_entrypoints_return_home_screen(): void
    {
        $this->get('/login')->assertRedirect(route('home'));
        $this->get('/register')->assertRedirect(route('home'));
    }

    public function test_authenticated_game_screen_returns_snapshot(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/game')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Game/Show')
                ->where('app.version', '0.1.0')
                ->has('game.data.user')
                ->where('game.data.user.level', 1)
                ->where('game.data.currentMap.name', 'Ithan')
            );
    }

    public function test_stage_battle_consumes_pa_and_returns_updated_snapshot(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/game')->assertOk();

        $spentAt = Carbon::parse('2026-06-20 13:37:00');
        Carbon::setTestNow($spentAt);

        try {
            $response = $this->postJson('/game/actions/battle/stage', [
                'locationId' => 'ithan-hunters-cave',
                'stage' => 1,
            ]);
        } finally {
            Carbon::setTestNow();
        }

        $response
            ->assertOk()
            ->assertJsonPath('game.user.pa', 19)
            ->assertJsonPath('battle.log.0.type', 'battle-start')
            ->assertJsonPath('battle.log.1.type', 'attack')
            ->assertJsonPath('battle.log.1.actor', 'player')
            ->assertJsonPath('battle.log.1.target', 'enemy')
            ->assertJsonStructure([
                'battle' => ['name', 'enemy', 'won', 'log', 'rewards'],
                'game' => ['user', 'currentMap', 'worldMaps', 'shops'],
            ])
            ->assertJsonMissingPath('battle.log.0.text')
            ->assertJsonMissingPath('battle.log.1.text');

        $profile = GameProfile::query()->whereBelongsTo($user)->firstOrFail();

        $this->assertSame(19, $profile->pa);
        $this->assertTrue($profile->pa_regenerated_at->equalTo($spentAt));
    }

    public function test_action_points_regenerate_over_time(): void
    {
        config()->set('game.action_points.regeneration_seconds', 60);
        config()->set('game.action_points.regeneration_limit', 20);

        $user = User::factory()->create();
        GameProfile::factory()->for($user)->create([
            'pa' => 10,
            'pa_max' => 20,
            'pa_regenerated_at' => now()->subSeconds(121),
        ]);

        $this->actingAs($user)
            ->getJson('/game/state')
            ->assertOk()
            ->assertJsonPath('data.user.pa', 12)
            ->assertJsonPath('data.user.paMax', 20)
            ->assertJsonPath('data.user.paLimit', 20)
            ->assertJsonPath('data.user.paRegenerationLimit', 20)
            ->assertJsonPath('data.user.paRegenerationSeconds', 60);

        $this->assertSame(12, GameProfile::query()->whereBelongsTo($user)->value('pa'));
    }

    public function test_action_point_regeneration_job_is_queued_for_profiles_below_limit(): void
    {
        config()->set('queue.default', 'redis');
        config()->set('game.action_points.regeneration_seconds', 60);
        config()->set('game.action_points.regeneration_limit', 20);
        Queue::fake();

        $profile = GameProfile::factory()->create([
            'pa' => 19,
            'pa_max' => 20,
            'pa_regenerated_at' => now(),
        ]);

        app(ActionPointRegenerationScheduler::class)->schedule($profile);

        Queue::assertPushedOn(
            'action-points',
            RegenerateActionPoints::class,
            fn (RegenerateActionPoints $job): bool => $job->profileId === $profile->id,
        );
    }

    public function test_queued_action_point_regeneration_broadcasts_restored_points(): void
    {
        config()->set('game.action_points.regeneration_seconds', 60);
        config()->set('game.action_points.regeneration_limit', 20);
        Event::fake([ActionPointsChanged::class]);

        $profile = GameProfile::factory()->create([
            'pa' => 19,
            'pa_max' => 20,
            'pa_regenerated_at' => now()->subSeconds(60),
        ]);

        (new RegenerateActionPoints($profile->id))->handle(
            app(GameProfileService::class),
            app(ActionPointRegenerationScheduler::class),
        );

        $profile->refresh();

        $this->assertSame(20, $profile->pa);
        Event::assertDispatched(
            ActionPointsChanged::class,
            fn (ActionPointsChanged $event): bool => $event->userId === $profile->user_id
                && $event->actionPoints['pa'] === 20
                && $event->actionPoints['paRegeneratesAt'] === null,
        );
    }

    public function test_action_points_regeneration_stops_at_configured_limit(): void
    {
        config()->set('game.action_points.regeneration_seconds', 60);
        config()->set('game.action_points.regeneration_limit', 20);

        $user = User::factory()->create();
        GameProfile::factory()->for($user)->create([
            'pa' => 19,
            'pa_max' => 50,
            'pa_regenerated_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->getJson('/game/state')
            ->assertOk()
            ->assertJsonPath('data.user.pa', 20)
            ->assertJsonPath('data.user.paMax', 50)
            ->assertJsonPath('data.user.paRegeneratesAt', null);

        $profile = GameProfile::query()->whereBelongsTo($user)->firstOrFail();

        $this->assertSame(20, $profile->pa);
        $this->assertSame(50, $profile->pa_max);
    }

    public function test_action_points_above_regeneration_limit_are_not_clamped(): void
    {
        config()->set('game.action_points.regeneration_seconds', 60);
        config()->set('game.action_points.regeneration_limit', 20);

        $user = User::factory()->create();
        GameProfile::factory()->for($user)->create([
            'pa' => 24,
            'pa_max' => 20,
            'pa_regenerated_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->getJson('/game/state')
            ->assertOk()
            ->assertJsonPath('data.user.pa', 24)
            ->assertJsonPath('data.user.paRegeneratesAt', null);

        $this->assertSame(24, GameProfile::query()->whereBelongsTo($user)->value('pa'));
    }

    public function test_level_up_can_raise_action_points_above_regeneration_limit(): void
    {
        config()->set('game.action_points.regeneration_limit', 20);

        $profile = GameProfile::factory()->create([
            'pa' => 19,
            'pa_max' => 20,
            'exp' => 0,
            'exp_max' => 20,
        ]);

        app(GameProfileService::class)->addExperience($profile, 20);

        $this->assertSame(24, $profile->refresh()->pa);
    }

    public function test_pa_potion_can_raise_action_points_above_regeneration_limit(): void
    {
        config()->set('game.action_points.regeneration_limit', 20);

        $user = User::factory()->create();
        GameProfile::factory()->for($user)->create([
            'pa' => 19,
            'pa_max' => 20,
            'inventory' => [
                [
                    'id' => 'test-pa-potion',
                    'name' => 'Testowa mikstura PA',
                    'type' => 'potion',
                    'effect' => 'pa',
                    'effectValue' => 5,
                    'price' => 0,
                    'quantity' => 1,
                ],
                ...array_fill(0, GameProfileService::INVENTORY_SIZE - 1, null),
            ],
        ]);

        $this->actingAs($user)
            ->postJson('/game/actions/inventory/use', ['index' => 0])
            ->assertOk()
            ->assertJsonPath('data.user.pa', 24)
            ->assertJsonPath('data.user.paRegeneratesAt', null);

        $profile = GameProfile::query()->whereBelongsTo($user)->firstOrFail();

        $this->assertSame(24, $profile->pa);
        $this->assertNull($profile->inventory[0]);
    }

    public function test_tavern_rest_starts_countdown_without_granting_action_points_immediately(): void
    {
        config()->set('queue.default', 'redis');
        config()->set('game.rest.options.1.duration_seconds', 60);
        Queue::fake();
        $now = Carbon::parse('2026-06-20 18:00:00');
        Carbon::setTestNow($now);

        try {
            $user = User::factory()->create();
            GameProfile::factory()->for($user)->create([
                'pa' => 8,
                'pa_regenerated_at' => $now,
            ]);

            $this->actingAs($user)
                ->postJson('/game/actions/rest', ['minutes' => 1])
                ->assertOk()
                ->assertJsonPath('data.user.pa', 8)
                ->assertJsonPath('data.rest.options.0.minutes', 1)
                ->assertJsonPath('data.rest.options.0.active', true)
                ->assertJsonPath('data.rest.options.0.endsAt', $now->copy()->addMinute()->toIso8601String());

            $profile = GameProfile::query()->whereBelongsTo($user)->firstOrFail();
            $this->assertSame(8, $profile->pa);
            $this->assertSame(2, $profile->rest_tasks['1']['action_points']);

            Queue::assertPushedOn(
                'action-points',
                CompleteRest::class,
                fn (CompleteRest $job): bool => $job->profileId === $profile->id
                    && $job->minutes === 1,
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_tavern_rest_rejects_restarting_the_same_active_option(): void
    {
        $now = Carbon::parse('2026-06-20 18:30:00');
        Carbon::setTestNow($now);

        try {
            $user = User::factory()->create();
            GameProfile::factory()->for($user)->create([
                'rest_tasks' => [
                    '5' => [
                        'minutes' => 5,
                        'action_points' => 12,
                        'ends_at' => $now->copy()->addMinutes(5)->toIso8601String(),
                    ],
                ],
            ]);

            $this->actingAs($user)
                ->postJson('/game/actions/rest', ['minutes' => 5])
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Ten odpoczynek już trwa.');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_tavern_rest_completion_grants_action_points_and_broadcasts_update(): void
    {
        $now = Carbon::parse('2026-06-20 19:00:00');
        Carbon::setTestNow($now);
        Event::fake([ActionPointsChanged::class]);

        try {
            $profile = GameProfile::factory()->create([
                'pa' => 8,
                'pa_regenerated_at' => $now->copy()->subMinute(),
                'rest_tasks' => [
                    '1' => [
                        'minutes' => 1,
                        'action_points' => 2,
                        'ends_at' => $now->toIso8601String(),
                    ],
                ],
            ]);

            (new CompleteRest($profile->id, 1))->handle(
                app(TavernRestService::class),
                app(ActionPointRegenerationScheduler::class),
            );

            $profile->refresh();

            $this->assertSame(10, $profile->pa);
            $this->assertSame([], $profile->rest_tasks);
            Event::assertDispatched(
                ActionPointsChanged::class,
                fn (ActionPointsChanged $event): bool => $event->userId === $profile->user_id
                    && $event->actionPoints['pa'] === 10,
            );
        } finally {
            Carbon::setTestNow();
        }
    }
}
