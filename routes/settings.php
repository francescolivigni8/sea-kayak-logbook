<?php

use App\Http\Controllers\CourseApplicationController;
use App\Http\Controllers\Settings\FeedbackController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware(array_filter([
    'auth',
    Features::enabled(Features::emailVerification()) ? 'verified' : null,
    'legal.accepted',
]))->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('settings/profile/backup', [ProfileController::class, 'backup'])
        ->middleware('throttle:3,1')
        ->name('profile.backup');
    Route::get('settings/profile/export', [ProfileController::class, 'export'])
        ->middleware('throttle:6,1')
        ->name('profile.export');
    Route::post('settings/profile/feedback', [FeedbackController::class, 'store'])
        ->middleware('throttle:8,1')
        ->name('profile.feedback.store');
    Route::get('settings/profile/report', [CourseApplicationController::class, 'report'])
        ->name('profile.report');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', fn () => redirect()->to(route('profile.edit').'#security'))->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', fn () => redirect()->to(route('profile.edit').'#appearance'))->name('appearance.edit');
});
