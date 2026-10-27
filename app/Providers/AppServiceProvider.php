<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\Models\TrustedDeviceEvent;
use App\Console\Commands\MediaLibraryCleanFoldersCommand;
use App\Modules\Admin\Models\AdminInvitation;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Models\Like;
use App\Modules\Post\Models\Post;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use DeviceDetector\DeviceDetector;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Override;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The morph name of Spatie's Role model, which is audited as an activity log subject.
     */
    public const string ROLE_MORPH_NAME = 'role';

    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(DeviceDetector::class, fn (Application $application): DeviceDetector => $this->buildDeviceDetector($application));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerMorphMap();
        $this->prohibitDestructiveCommandsInProduction();
    }

    /**
     * Map the polymorphic aliases stored in the database to their models,
     * rejecting any model without an alias.
     */
    private function registerMorphMap(): void
    {
        Relation::enforceMorphMap([
            User::MORPH_NAME => User::class,
            Post::MORPH_NAME => Post::class,
            Like::MORPH_NAME => Like::class,
            Comment::MORPH_NAME => Comment::class,
            AdminInvitation::MORPH_NAME => AdminInvitation::class,
            TrustedDeviceEvent::MORPH_NAME => TrustedDeviceEvent::class,
            AccountStatusChange::MORPH_NAME => AccountStatusChange::class,
            Announcement::MORPH_NAME => Announcement::class,
            self::ROLE_MORPH_NAME => Role::class,
        ]);
    }

    /**
     * Block the Artisan commands that destroy data when running in production.
     */
    private function prohibitDestructiveCommandsInProduction(): void
    {
        MediaLibraryCleanFoldersCommand::prohibit($this->app->isProduction());
    }

    /**
     * Build and configure an instance of DeviceDetector.
     */
    private function buildDeviceDetector(Application $application): DeviceDetector
    {
        $request = $application->make(Request::class);

        $deviceDetector = new DeviceDetector($request->userAgent() ?? '');
        $deviceDetector->parse();

        return $deviceDetector;
    }
}
