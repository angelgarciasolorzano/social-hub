<?php

declare(strict_types=1);

namespace App\Modules\Admin\Exceptions;

use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use RuntimeException;

/**
 * A role or permission change that `AdminAuthorizationService` refused; nothing was written.
 */
final class AdminAuthorizationException extends RuntimeException
{
    public function __construct(public readonly AdminAuthorizationFailure $failure)
    {
        parent::__construct("The Admin authorization change was refused: {$failure->value}.");
    }
}
