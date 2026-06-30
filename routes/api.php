<?php

use App\Http\Controllers\Admin\MatchmakingController;
use App\Http\Controllers\Admin\PemainController;
use App\Http\Controllers\Admin\PertandinganController;
use App\Http\Controllers\Api\Guest\BracketController;
use App\Http\Controllers\Api\Guest\RegistrationController;
use App\Http\Controllers\Api\Guest\StandingsController;
use App\Http\Controllers\Api\Guest\TournamentController;
use App\Http\Controllers\Api\V1\External\RegistrationController as ExternalRegistrationController;
use App\Http\Controllers\Api\V1\External\TournamentController as ExternalTournamentController;
use Illuminate\Support\Facades\Route;

// Guest API
Route::prefix('guest')->group(function () {
    Route::get('/tournaments/active', [TournamentController::class, 'active']);
    Route::get('/tournaments/open', [TournamentController::class, 'open']);
    Route::post('/register', [RegistrationController::class, 'store']);
    Route::get('/standings', [StandingsController::class, 'index'])->name('api.guest.standings');
    Route::get('/bracket', [BracketController::class, 'index'])->name('api.guest.bracket');
});

// External integration API (API key / Bearer token)
Route::prefix('v1/external')->middleware('external.api')->group(function () {
    Route::post('/register-player', [ExternalRegistrationController::class, 'registerPlayer']);
    Route::get('/tournaments/{id}/group-standings', [ExternalTournamentController::class, 'groupStandings']);
    Route::get('/tournaments/{id}/winners', [ExternalTournamentController::class, 'winners']);
});

// Admin API (session-authenticated via Sanctum stateful or token)
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('/pemain', [PemainController::class, 'index']);
    Route::patch('/pemain/{pemain}/status', [PemainController::class, 'updateStatus']);
    Route::delete('/pemain/{pemain}', [PemainController::class, 'destroy']);

    Route::post('/matchmaking/close-registration', [MatchmakingController::class, 'closeRegistration']);
    Route::post('/matchmaking/random-grup', [MatchmakingController::class, 'randomGrup']);
    Route::post('/matchmaking/end-group-stage', [MatchmakingController::class, 'endGroupStage']);
    Route::post('/matchmaking/reshuffle-groups', [MatchmakingController::class, 'reshuffleGroups']);
    Route::patch('/matchmaking/grup-member/{member}/points', [MatchmakingController::class, 'updateMahjongPoints']);
    Route::post('/matchmaking/complete-tournament', [MatchmakingController::class, 'completeTournament']);

    Route::get('/pertandingan/{pertandingan}', [PertandinganController::class, 'show']);
    Route::post('/pertandingan/{pertandingan}/score', [PertandinganController::class, 'storeScore']);
});
