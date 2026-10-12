<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Support;

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use Filament\Facades\Filament;
use Tests\TestCase;

final class AdminFilamentTestContext
{
    public static function configure(TestCase $testCase): void
    {
        $testCase->seed(AdminAuthorizationSeeder::class);

        Filament::setCurrentPanel('admin');
    }
}
