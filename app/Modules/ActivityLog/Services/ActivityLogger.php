<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Services;

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Single entry point to Spatie's activity log: no other module calls `activity()` directly.
 */
final class ActivityLogger
{
    /**
     * Property keys that could carry content, credentials or secrets and are never stored.
     */
    private const array FORBIDDEN_PROPERTY_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'token_hash',
        'code',
        'otp_code',
        'recovery_code',
        'recovery_codes',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'content',
        'email',
    ];

    private const int USER_AGENT_MAX_LENGTH = 512;

    /**
     * Record the event with its subject and causer; a null causer is stored as anonymous
     * so the package never guesses the authenticated user of another guard.
     *
     * @param  array<string, scalar|list<scalar>|null>  $properties
     *
     * @throws InvalidArgumentException when a property key is forbidden
     */
    public function record(
        ActivityLogEvent $activityLogEvent,
        ?Model $subject = null,
        ?Model $causer = null,
        array $properties = [],
    ): void {
        $this->assertPropertiesAreAllowed($properties);

        $activityLogger = activity($activityLogEvent->logName()->value)
            ->event($activityLogEvent->eventName())
            ->withProperties([...$properties, ...$this->requestContext()]);

        if ($subject instanceof Model) {
            $activityLogger->performedOn($subject);
        }

        if ($causer instanceof Model) {
            $activityLogger->causedBy($causer);
        } else {
            $activityLogger->causedByAnonymous();
        }

        $activityLogger->log($activityLogEvent->eventName());
    }

    /**
     * @param  array<string, scalar|list<scalar>|null>  $properties
     */
    private function assertPropertiesAreAllowed(array $properties): void
    {
        foreach (array_keys($properties) as $propertyKey) {
            throw_if(\in_array(mb_strtolower($propertyKey), self::FORBIDDEN_PROPERTY_KEYS, true), InvalidArgumentException::class, "The activity log property \"{$propertyKey}\" is not allowed.");
        }
    }

    /**
     * IP and user agent of the routed HTTP request being handled; empty for console runs.
     *
     * @return array{ip?: string|null, user_agent?: string|null}
     */
    private function requestContext(): array
    {
        $request = request();

        if ($request->route() === null) {
            return [];
        }

        $userAgent = $request->userAgent();

        return [
            'ip' => $request->ip(),
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, self::USER_AGENT_MAX_LENGTH),
        ];
    }
}
