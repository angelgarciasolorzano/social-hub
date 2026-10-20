<?php

declare(strict_types=1);

use App\Modules\Admin\Livewire\AcceptAdminInvitation;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::livewire('admin/invitations/{token}', AcceptAdminInvitation::class)
        ->name('admin.invitations.accept');
});
