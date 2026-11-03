<?php

declare(strict_types=1);

namespace App\Modules\Admin\Console\Concerns;

use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Console\Command;

/**
 * @phpstan-require-extends Command
 */
trait InteractsWithAdminCatalog
{
    protected const string ACTION_GRANT = 'grant';

    protected const string ACTION_REVOKE = 'revoke';

    protected const string GUARD = 'admin';

    protected function isValidAction(mixed $action): bool
    {
        return \in_array($action, [self::ACTION_GRANT, self::ACTION_REVOKE], true);
    }

    /**
     * The `--actor` email recorded as the person who ran the command.
     * Reports the error and returns null when the option is missing.
     */
    protected function actorEmail(mixed $actorEmail): ?string
    {
        if (! \is_string($actorEmail) || trim($actorEmail) === '') {
            $this->error('The --actor option is required: pass the email of the administrator running this command.');

            return null;
        }

        return trim($actorEmail);
    }

    /**
     * Find the verified account behind `--actor`, which must hold admin.access unless
     * the command bootstraps the first administrator. Reports why it is rejected.
     */
    protected function resolveActor(string $actorEmail, bool $requiresAdminAccess = true): ?User
    {
        $actor = User::query()->where('email', $actorEmail)->first();

        if (! $actor instanceof User) {
            $this->error("No actor was found with the email {$actorEmail}.");

            return null;
        }

        if (! $actor->hasVerifiedEmail()) {
            $this->error("The actor {$actorEmail} must have a verified email.");

            return null;
        }

        if ($requiresAdminAccess && ! $actor->can(AdminPermission::AdminAccess->value)) {
            $this->error("The actor {$actorEmail} must have admin.access.");

            return null;
        }

        return $actor;
    }

    /**
     * The console message of a catalog that `AdminAuthorizationService` found incomplete, or null for a
     * failure the command reports in its own words.
     */
    protected function catalogFailureMessage(
        AdminAuthorizationFailure $adminAuthorizationFailure,
        AdminRole $adminRole,
        ?AdminPermission $adminPermission = null,
    ): ?string {
        return match ($adminAuthorizationFailure) {
            AdminAuthorizationFailure::CatalogNotInitialized => 'Admin authorization is not initialized. Run the Admin authorization seeder first.',
            AdminAuthorizationFailure::RoleNotInitialized => "The Admin role {$adminRole->value} is not initialized. Run the Admin authorization seeder first.",
            AdminAuthorizationFailure::PermissionNotInitialized => "The Admin permission {$adminPermission?->value} is not initialized. Run the Admin authorization seeder first.",
            default => null,
        };
    }
}
