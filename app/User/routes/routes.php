<?php

namespace App\User\routes;

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    require __DIR__.'/profile.php';
    require __DIR__.'/user.php';
    require __DIR__.'/preference.php';
});
