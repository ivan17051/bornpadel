<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\BracketController as AdminBracketController;
use App\Http\Controllers\Admin\MatchmakingController;
use App\Http\Controllers\Admin\PemainController;
use App\Http\Controllers\Admin\PertandinganController;
use App\Http\Controllers\Admin\StandingsController as AdminStandingsController;
use App\Http\Controllers\Admin\TurnamenController;
use App\Http\Controllers\Guest\BracketController;
use App\Http\Controllers\Guest\LandingController;
use App\Http\Controllers\Guest\RegistrationController;
use App\Http\Controllers\Guest\StandingsController;
use Illuminate\Support\Facades\Route;

// Public guest routes
Route::get('/', [LandingController::class, 'index'])->name('guest.landing');
Route::get('/register', [RegistrationController::class, 'create'])->name('guest.register');
Route::post('/register/lookup', [RegistrationController::class, 'lookup'])->name('guest.register.lookup');
Route::get('/register/form', [RegistrationController::class, 'form'])->name('guest.register.form');
Route::post('/register', [RegistrationController::class, 'store'])->name('guest.register.store');
Route::get('/register/success', [RegistrationController::class, 'success'])->name('guest.register.success');
Route::get('/standings', [StandingsController::class, 'index'])->name('guest.standings');
Route::get('/bracket', [BracketController::class, 'index'])->name('guest.bracket');

// Admin auth
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/turnamen', [TurnamenController::class, 'index'])->name('turnamen.index');
        Route::get('/turnamen/create', [TurnamenController::class, 'create'])->name('turnamen.create');
        Route::post('/turnamen', [TurnamenController::class, 'store'])->name('turnamen.store');
        Route::get('/turnamen/{turnamen}/edit', [TurnamenController::class, 'edit'])->name('turnamen.edit');
        Route::put('/turnamen/{turnamen}', [TurnamenController::class, 'update'])->name('turnamen.update');
        Route::delete('/turnamen/{turnamen}', [TurnamenController::class, 'destroy'])->name('turnamen.destroy');

        Route::get('/pemain', [PemainController::class, 'index'])->name('pemain.index');
        Route::get('/pemain/create', [PemainController::class, 'create'])->name('pemain.create');
        Route::post('/pemain', [PemainController::class, 'store'])->name('pemain.store');
        Route::get('/pemain/{pemain}/edit', [PemainController::class, 'edit'])->name('pemain.edit');
        Route::patch('/pemain/{pemain}', [PemainController::class, 'update'])->name('pemain.update');
        Route::patch('/pemain/{pemain}/status', [PemainController::class, 'updateStatus'])->name('pemain.status');
        Route::delete('/pemain/{pemain}', [PemainController::class, 'destroy'])->name('pemain.destroy');

        Route::get('/matchmaking', [MatchmakingController::class, 'index'])->name('matchmaking.index');
        Route::post('/matchmaking/close-registration', [MatchmakingController::class, 'closeRegistration'])->name('matchmaking.close-registration');
        Route::post('/matchmaking/random-grup', [MatchmakingController::class, 'randomGrup'])->name('matchmaking.random-grup');
        Route::post('/matchmaking/end-group-stage', [MatchmakingController::class, 'endGroupStage'])->name('matchmaking.end-group-stage');

        Route::get('/bracket', [AdminBracketController::class, 'index'])->name('bracket.index');
        Route::get('/pertandingan', [PertandinganController::class, 'index'])->name('pertandingan.index');
        Route::get('/pertandingan/{pertandingan}', [PertandinganController::class, 'show'])->name('pertandingan.show');
        Route::post('/pertandingan/{pertandingan}/score', [PertandinganController::class, 'storeScore'])->name('pertandingan.score');

        Route::get('/standings', [AdminStandingsController::class, 'index'])->name('standings.index');
    });
});
