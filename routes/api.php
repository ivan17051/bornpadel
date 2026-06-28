<?php

use App\Http\Controllers\Admin\MatchmakingController;
use App\Http\Controllers\Admin\PemainController;
use App\Http\Controllers\Admin\PertandinganController;
use App\Http\Controllers\Api\Guest\BracketController;
use App\Http\Controllers\Api\Guest\RegistrationController;
use App\Http\Controllers\Api\Guest\StandingsController;
use App\Http\Controllers\Api\Guest\TournamentController;
use Illuminate\Support\Facades\Route;

// Guest API
Route::prefix('guest')->group(function () {
    Route::get('/tournaments/active', [TournamentController::class, 'active']);
    Route::get('/tournaments/open', [TournamentController::class, 'open']);
    Route::post('/register', [RegistrationController::class, 'store']);
    Route::get('/standings', [StandingsController::class, 'index'])->name('api.guest.standings');
    Route::get('/bracket', [BracketController::class, 'index'])->name('api.guest.bracket');
});

// Admin API (session-authenticated via Sanctum stateful or token)
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('/pemain', [PemainController::class, 'index']);
    Route::patch('/pemain/{pemain}/status', [PemainController::class, 'updateStatus']);
    Route::delete('/pemain/{pemain}', [PemainController::class, 'destroy']);

    Route::post('/matchmaking/close-registration', [MatchmakingController::class, 'closeRegistration']);
    Route::post('/matchmaking/random-grup', [MatchmakingController::class, 'randomGrup']);
    Route::post('/matchmaking/end-group-stage', [MatchmakingController::class, 'endGroupStage']);
    Route::post('/matchmaking/complete-tournament', [MatchmakingController::class, 'completeTournament']);

    Route::get('/pertandingan/{pertandingan}', [PertandinganController::class, 'show']);
    Route::post('/pertandingan/{pertandingan}/score', [PertandinganController::class, 'storeScore']);
});
