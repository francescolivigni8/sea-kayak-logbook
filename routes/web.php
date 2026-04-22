<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\ExpeditionPlaceController;
use App\Http\Controllers\GarminImportController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\JournalNotesController;
use App\Http\Controllers\PaddleSessionController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\SessionCategoryController;
use App\Http\Controllers\UserInsightsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return $request->user()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::view('/privacy', 'legal.privacy')->name('legal.privacy');
Route::view('/terms', 'legal.terms')->name('legal.terms');
Route::view('/contact', 'legal.contact')->name('legal.contact');
Route::get('/health', HealthCheckController::class)->name('health');

Route::get('/p/{profile:slug}', PublicProfileController::class)->name('profiles.public.show');
Route::get('/p/{profile:slug}/expeditions', [ExpeditionPlaceController::class, 'publicIndex'])
    ->name('profiles.public.expeditions.index');
Route::get('/p/{profile:slug}/expeditions/{place}', [ExpeditionPlaceController::class, 'publicShow'])
    ->name('profiles.public.expeditions.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('workspace', function (Request $request) {
        return response()->view('workspace', [
            'user' => $request->user(),
        ]);
    })->name('workspace');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('diary', DiaryController::class)->name('diary');
    Route::get('observations', [JournalNotesController::class, 'observations'])->name('observations');
    Route::get('expedition-notes', [JournalNotesController::class, 'expeditionNotes'])->name('expedition-notes');
    Route::get('imports/garmin', [GarminImportController::class, 'create'])->name('imports.garmin.create');
    Route::post('imports/garmin', [GarminImportController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('imports.garmin.store');
    Route::get('expeditions', [ExpeditionPlaceController::class, 'index'])->name('expeditions.index');
    Route::get('expeditions/{place}', [ExpeditionPlaceController::class, 'show'])->name('expeditions.show');
    Route::get('planning', [PlanningController::class, 'index'])->name('planning.index');
    Route::post('planning', [PlanningController::class, 'store'])->name('planning.store');
    Route::get('planning/{plannedSession}/edit', [PlanningController::class, 'edit'])->name('planning.edit');
    Route::put('planning/{plannedSession}', [PlanningController::class, 'update'])->name('planning.update');
    Route::get('planning/weather-preview', [PlanningController::class, 'weatherPreview'])
        ->middleware('throttle:20,1')
        ->name('planning.weather-preview');
    Route::get('sessions/weather-preview', [PaddleSessionController::class, 'weatherPreview'])
        ->middleware('throttle:20,1')
        ->name('sessions.weather-preview');
    Route::post('session-categories', [SessionCategoryController::class, 'store'])
        ->name('session-categories.store');
    Route::post(
        'session-categories/{sessionCategory}/sessions',
        [SessionCategoryController::class, 'attachSessions'],
    )->name('session-categories.sessions.attach-many');
    Route::post(
        'session-categories/{sessionCategory}/sessions/{session}',
        [SessionCategoryController::class, 'attachSession'],
    )->name('session-categories.sessions.attach');
    Route::get('insights/users', UserInsightsController::class)
        ->middleware('journal.owner')
        ->name('insights.users');

    Route::resource('sessions', PaddleSessionController::class)
        ->except(['destroy']);
});

require __DIR__.'/settings.php';
