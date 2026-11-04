<?php

declare(strict_types=1);

namespace App\Modules\Notification\Props;

use App\User\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;

final readonly class NotificationProps implements ProvidesInertiaProperties
{
    public function __construct(private Request $request) {}

    /**
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'unreadNotificationsCount' => Inertia::defer(function (): int {
                $user = $this->request->user();

                if (! $user instanceof User) {
                    return 0;
                }

                return $user->unreadNotifications()->count();
            }),
        ];
    }
}
