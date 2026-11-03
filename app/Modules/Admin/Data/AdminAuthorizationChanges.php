<?php

declare(strict_types=1);

namespace App\Modules\Admin\Data;

use Spatie\LaravelData\Data;

/**
 * What a panel sync of roles or role permissions actually changed: the cataloged values it granted and
 * the ones it revoked, in catalog order.
 */
final class AdminAuthorizationChanges extends Data
{
    /**
     * @param  list<string>  $granted
     * @param  list<string>  $revoked
     */
    public function __construct(
        public array $granted,
        public array $revoked,
    ) {}

    public function hasChanges(): bool
    {
        return $this->changeCount() > 0;
    }

    public function changeCount(): int
    {
        return \count($this->granted) + \count($this->revoked);
    }
}
