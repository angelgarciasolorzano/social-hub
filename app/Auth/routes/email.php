<?php

declare(strict_types=1);

use App\Auth\Email\Controllers\EmailVerificationNotificationController;
use App\Auth\Email\Controllers\EmailVerificationPromptController;
use App\Auth\Email\Controllers\EmailVerifyController;
use Illuminate\Support\Facades\Route;

Route::get('verify-email', EmailVerificationPromptController::class)
    ->name('verification.notice');

Route::get('verify-email/{id}/{hash}', EmailVerifyController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('verification.send');
