<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    Filament::setCurrentPanel('admin');
});

it('rejects direct user create edit and delete requests without mutating users', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $targetUser = createUser();
    $originalName = $targetUser->name;
    $originalEmail = $targetUser->email;
    $originalUserCount = User::query()->count('id');

    $this->actingAs($authenticatedUser, 'admin');

    $this->get('/admin/users/create')->assertNotFound();
    $this->get('/admin/users/'.$targetUser->id.'/edit')->assertNotFound();

    $this->post('/admin/users')
        ->assertMethodNotAllowed();

    $this->patch('/admin/users/'.$targetUser->id, [
        'name' => 'Unauthorized Name Change',
        'email' => 'unauthorized-change@example.test',
    ])
        ->assertMethodNotAllowed();

    $this->delete('/admin/users/'.$targetUser->id)
        ->assertMethodNotAllowed();

    expect(User::query()->count('id'))->toBe($originalUserCount)
        ->and($targetUser->refresh()->name)->toBe($originalName)
        ->and($targetUser->email)->toBe($originalEmail);
});
