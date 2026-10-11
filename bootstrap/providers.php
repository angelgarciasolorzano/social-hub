<?php

declare(strict_types=1);

use App\Auth\Providers\AuthServiceProvider;
use App\Friendship\Providers\FriendshipServiceProvider;
use App\Home\Providers\HomeServiceProvider;
use App\Modules\Admin\Providers\AdminPanelProvider;
use App\Modules\Admin\Providers\AdminServiceProvider;
use App\Modules\Comment\Providers\CommentServiceProvider;
use App\Modules\Post\Providers\PostServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\User\Providers\UserServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    AdminServiceProvider::class,
    AdminPanelProvider::class,
    HomeServiceProvider::class,
    UserServiceProvider::class,
    PostServiceProvider::class,
    CommentServiceProvider::class,
    FriendshipServiceProvider::class,
    FortifyServiceProvider::class,
];
