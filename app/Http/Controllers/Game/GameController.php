<?php

namespace App\Http\Controllers\Game;

use App\Game\Services\ActionPointRegenerationScheduler;
use App\Game\Services\GameProfileService;
use App\Game\Services\GameStateService;
use App\Http\Controllers\Controller;
use App\Http\Resources\GameSnapshotResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GameController extends Controller
{
    public function show(Request $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints): Response
    {
        $profile = $profiles->ensureFor($request->user());
        $actionPoints->schedule($profile);

        return Inertia::render('Game/Show', [
            'game' => new GameSnapshotResource($gameState->snapshot($profile)),
        ]);
    }

    public function state(Request $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints): GameSnapshotResource
    {
        $profile = $profiles->ensureFor($request->user());
        $actionPoints->schedule($profile);

        return new GameSnapshotResource($gameState->snapshot($profile));
    }
}
