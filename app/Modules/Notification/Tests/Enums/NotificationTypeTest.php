<?php

declare(strict_types=1);

use App\Modules\Notification\Enums\NotificationType;

it('exposes the supported notification types', function (): void {
    expect(NotificationType::values())->toBe([
        'comment_on_post',
        'comment_reply',
        'likes_grouped',
    ]);
});

it('rejects notification types outside the supported set', function (string $unknownType): void {
    expect(NotificationType::tryFrom($unknownType))->toBeNull()
        ->and(NotificationType::values())->not->toContain($unknownType);
})->with([
    'Filament notification' => ['filament.database-notification'],
    'unknown notification' => ['unknown.notification'],
]);
