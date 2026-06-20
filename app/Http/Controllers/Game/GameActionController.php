<?php

namespace App\Http\Controllers\Game;

use App\Game\Enums\ArenaDifficulty;
use App\Game\Enums\PlayerAttribute;
use App\Game\Services\ActionPointRegenerationScheduler;
use App\Game\Services\BattleService;
use App\Game\Services\GameProfileService;
use App\Game\Services\GameStateService;
use App\Game\Services\InventoryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\ArenaBattleRequest;
use App\Http\Requests\Game\AttributeRequest;
use App\Http\Requests\Game\BuyItemRequest;
use App\Http\Requests\Game\BuyPaRequest;
use App\Http\Requests\Game\InventorySlotRequest;
use App\Http\Requests\Game\RestRequest;
use App\Http\Requests\Game\StageBattleRequest;
use App\Http\Requests\Game\UnequipRequest;
use App\Http\Requests\Game\WorldMapRequest;
use App\Http\Resources\BattleResultResource;
use App\Http\Resources\GameSnapshotResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GameActionController extends Controller
{
    public function stageBattle(StageBattleRequest $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints, BattleService $battles): JsonResponse
    {
        return $this->respondWithBattle($request, $profiles, $gameState, $actionPoints, fn ($profile) => $battles->fightStage(
            $profile,
            $request->string('locationId')->toString(),
            $request->integer('stage'),
        ));
    }

    public function arenaBattle(ArenaBattleRequest $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints, BattleService $battles): JsonResponse
    {
        return $this->respondWithBattle($request, $profiles, $gameState, $actionPoints, fn ($profile) => $battles->fightArena(
            $profile,
            ArenaDifficulty::from($request->string('difficulty')->toString()),
        ));
    }

    public function addAttribute(AttributeRequest $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints): JsonResponse
    {
        return $this->respondWithSnapshot($request, $profiles, $gameState, $actionPoints, fn ($profile) => $profiles->addAttribute(
            $profile,
            PlayerAttribute::from($request->string('attribute')->toString()),
        ));
    }

    public function selectMap(WorldMapRequest $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints): JsonResponse
    {
        return $this->respondWithSnapshot($request, $profiles, $gameState, $actionPoints, fn ($profile) => $gameState->selectMap(
            $profile,
            $request->integer('mapId'),
        ));
    }

    public function rest(RestRequest $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints): JsonResponse
    {
        return $this->respondWithSnapshot($request, $profiles, $gameState, $actionPoints, fn ($profile) => $gameState->rest(
            $profile,
            $request->integer('minutes'),
        ));
    }

    public function instantRest(Request $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints): JsonResponse
    {
        return $this->respondWithSnapshot($request, $profiles, $gameState, $actionPoints, fn ($profile) => $gameState->instantRest($profile));
    }

    public function buyPa(BuyPaRequest $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints): JsonResponse
    {
        return $this->respondWithSnapshot($request, $profiles, $gameState, $actionPoints, fn ($profile) => $gameState->buyPa(
            $profile,
            $request->integer('amount'),
            $request->integer('price'),
        ));
    }

    public function buyItem(BuyItemRequest $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints, InventoryService $inventory): JsonResponse
    {
        return $this->respondWithSnapshot($request, $profiles, $gameState, $actionPoints, fn ($profile) => $inventory->buyItem(
            $profile,
            $request->string('shopId')->toString(),
            $request->input('itemId'),
        ));
    }

    public function equip(InventorySlotRequest $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints, InventoryService $inventory): JsonResponse
    {
        return $this->respondWithSnapshot($request, $profiles, $gameState, $actionPoints, fn ($profile) => $inventory->equip(
            $profile,
            $request->integer('index'),
        ));
    }

    public function unequip(UnequipRequest $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints, InventoryService $inventory): JsonResponse
    {
        return $this->respondWithSnapshot($request, $profiles, $gameState, $actionPoints, fn ($profile) => $inventory->unequip(
            $profile,
            $request->string('slot')->toString(),
        ));
    }

    public function sell(InventorySlotRequest $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints, InventoryService $inventory): JsonResponse
    {
        return $this->respondWithSnapshot($request, $profiles, $gameState, $actionPoints, fn ($profile) => $inventory->sell(
            $profile,
            $request->integer('index'),
        )['profile']);
    }

    public function useItem(InventorySlotRequest $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints, InventoryService $inventory): JsonResponse
    {
        return $this->respondWithSnapshot($request, $profiles, $gameState, $actionPoints, fn ($profile) => $inventory->useItem(
            $profile,
            $request->integer('index'),
        ));
    }

    private function respondWithSnapshot(Request $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints, callable $action): JsonResponse
    {
        try {
            $profile = $profiles->ensureFor($request->user());
            $action($profile);
            $profile->refresh();
            $actionPoints->schedule($profile);

            return (new GameSnapshotResource($gameState->snapshot($profile)))->response();
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function respondWithBattle(Request $request, GameProfileService $profiles, GameStateService $gameState, ActionPointRegenerationScheduler $actionPoints, callable $action): JsonResponse
    {
        try {
            $profile = $profiles->ensureFor($request->user());
            $battle = $action($profile);
            $profile->refresh();
            $actionPoints->schedule($profile);

            return response()->json([
                'battle' => (new BattleResultResource($battle))->resolve(),
                'game' => (new GameSnapshotResource($gameState->snapshot($profile)))->resolve(),
            ]);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
