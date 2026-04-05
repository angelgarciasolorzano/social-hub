<?php

use App\Auth\Providers\AuthServiceProvider;
use App\Comment\Providers\CommentServiceProvider;
use App\Friendship\Providers\FriendshipServiceProvider;
use App\MediaLibrary\Providers\MediaLibraryServiceProvider;
use App\Post\Providers\PostServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\User\Providers\UserServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    UserServiceProvider::class,
    PostServiceProvider::class,
    CommentServiceProvider::class,
    FriendshipServiceProvider::class,
    MediaLibraryServiceProvider::class,
    FortifyServiceProvider::class,
];
