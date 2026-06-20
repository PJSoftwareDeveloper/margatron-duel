<?php

namespace App\Http\Controllers\Game;

use App\Game\Services\AchievementService;
use App\Game\Services\ActionPointRegenerationScheduler;
use App\Game\Services\GameProfileService;
use App\Game\Services\GameStateService;
use App\Http\Controllers\Controller;
use App\Http\Resources\AchievementResource;
use App\Http\Resources\GameSnapshotResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AchievementController extends Controller
{
    public function index(
        Request $request,
        GameProfileService $profiles,
        GameStateService $gameState,
        ActionPointRegenerationScheduler $actionPoints,
        AchievementService $achievements,
    ): Response {
        $profile = $profiles->ensureFor($request->user());
        $actionPoints->schedule($profile);
        $progress = $achievements->forProfile($profile);

        return Inertia::render('Game/Achievements', [
            'game' => new GameSnapshotResource($gameState->snapshot($profile)),
            'achievements' => [
                'entries' => AchievementResource::collection($progress->entries)->resolve($request),
                'completedCount' => $progress->completedCount,
                'totalCount' => $progress->totalCount,
                'overallPercent' => $progress->overallPercent,
            ],
        ]);
    }
}
