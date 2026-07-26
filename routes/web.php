<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Game\AchievementController;
use App\Http\Controllers\Game\GameActionController;
use App\Http\Controllers\Game\GameController;
use App\Http\Controllers\Game\RankingController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('game.show');
    }

    return Inertia::render('Home');
})->name('home');

Route::get('/login', fn () => redirect()->route('home'));
Route::get('/register', fn () => redirect()->route('home'));

Route::middleware('guest')->group(function (): void {
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/game', [GameController::class, 'show'])->name('game.show');
    Route::get('/game/state', [GameController::class, 'state'])->name('game.state');
    Route::get('/rankings', [RankingController::class, 'index'])->name('rankings.index');
    Route::get('/achievements', [AchievementController::class, 'index'])->name('achievements.index');

    Route::prefix('/game/actions')->name('game.actions.')->group(function (): void {
        Route::post('/battle/stage', [GameActionController::class, 'stageBattle'])->name('battle.stage');
        Route::post('/battle/arena', [GameActionController::class, 'arenaBattle'])->name('battle.arena');
        Route::post('/battle/tough', [GameActionController::class, 'toughBattle'])->name('battle.tough');
        Route::post('/attribute', [GameActionController::class, 'addAttribute'])->name('attribute');
        Route::post('/map', [GameActionController::class, 'selectMap'])->name('map');
        Route::post('/rest', [GameActionController::class, 'rest'])->name('rest');
        Route::post('/rest/instant', [GameActionController::class, 'instantRest'])->name('rest.instant');
        Route::post('/pa', [GameActionController::class, 'buyPa'])->name('pa');
        Route::post('/shop/buy', [GameActionController::class, 'buyItem'])->name('shop.buy');
        Route::post('/inventory/equip', [GameActionController::class, 'equip'])->name('inventory.equip');
        Route::post('/inventory/unequip', [GameActionController::class, 'unequip'])->name('inventory.unequip');
        Route::post('/inventory/sell', [GameActionController::class, 'sell'])->name('inventory.sell');
        Route::post('/inventory/use', [GameActionController::class, 'useItem'])->name('inventory.use');
    });
});
