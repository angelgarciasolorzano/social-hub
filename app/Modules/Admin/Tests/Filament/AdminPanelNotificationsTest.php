<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Filament\Notifications\Notification as FilamentNotification;

it('enables database notifications in the admin panel with Filament polling', function (): void {
    $adminPanel = Filament::getPanel('admin');

    expect($adminPanel->hasDatabaseNotifications())->toBeTrue()
        ->and($adminPanel->getDatabaseNotificationsPollingInterval())->toBe('30s');
});

it('stores Filament notifications for the intended user', function (): void {
    $user = createUser();

    FilamentNotification::make()
        ->title('Announcement submitted for review')
        ->sendToDatabase($user);

    $databaseNotification = $user->notifications()->sole();

    $this->assertDatabaseHas('notifications', [
        'id' => $databaseNotification->id,
        'notifiable_id' => $user->id,
        'notifiable_type' => $user->getMorphClass(),
    ]);

    expect($databaseNotification->type)->toBe(FilamentDatabaseNotification::class)
        ->and($databaseNotification->data['title'])->toBe('Announcement submitted for review');
});
