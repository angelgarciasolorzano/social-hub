<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Enums;

use App\Support\Translation\TranslatedText;
use Symfony\Component\HttpFoundation\Response;

/**
 * The outcome of a friendship action: the value sent to the client, its HTTP status and its message.
 */
enum FriendshipOperationResult: string
{
    case Sent = 'sent';

    case Accepted = 'accepted';

    case Declined = 'declined';

    case Canceled = 'canceled';

    case Removed = 'removed';

    case AlreadyPending = 'already_pending';

    case AlreadyFriends = 'already_friends';

    case IncomingRequestExists = 'incoming_request_exists';

    case CooldownActive = 'cooldown_active';

    case PendingLimitReached = 'pending_limit_reached';

    case ReceiverUnavailable = 'receiver_unavailable';

    case NoLongerPending = 'no_longer_pending';

    /**
     * Whether the action changed something; every other case was refused by a business rule.
     */
    public function isSuccessful(): bool
    {
        return match ($this) {
            self::Sent, self::Accepted, self::Declined, self::Canceled, self::Removed => true,
            self::AlreadyPending,
            self::AlreadyFriends,
            self::IncomingRequestExists,
            self::CooldownActive,
            self::PendingLimitReached,
            self::ReceiverUnavailable,
            self::NoLongerPending => false,
        };
    }

    /**
     * Whether the case ends a friendship or a request, the reasons `FriendshipEnded` accepts.
     */
    public function endsFriendship(): bool
    {
        return match ($this) {
            self::Declined, self::Canceled, self::Removed => true,
            default => false,
        };
    }

    /**
     * The HTTP status of the response: 200 for a change, 409 for a refusal.
     */
    public function httpStatus(): int
    {
        return $this->isSuccessful() ? Response::HTTP_OK : Response::HTTP_CONFLICT;
    }

    /**
     * The translation key of the result's message.
     */
    public function messageKey(): string
    {
        return 'friendship/actions.results.'.$this->value;
    }

    /**
     * The message of the result, in the active language.
     */
    public function message(): string
    {
        return TranslatedText::of($this->messageKey());
    }
}
