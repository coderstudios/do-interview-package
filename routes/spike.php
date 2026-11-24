<?php

use CoderStudios\DoInterview\Http\Controllers\SpikeController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('do-interview.spike.middleware', ['web', 'auth']))
    ->prefix(config('do-interview.spike.prefix', 'interview-spike'))
    ->name('do-interview.spike.')
    ->group(function () {
        Route::get('/', [SpikeController::class, 'show'])->name('show');
        Route::post('/session', [SpikeController::class, 'start'])
            ->middleware('throttle:do-interview-spike')
            ->name('session');
    });
