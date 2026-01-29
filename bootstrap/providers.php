<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Auth\Providers\AuthServiceProvider::class,
    App\User\Providers\UserServiceProvider::class,
    App\Post\Providers\PostServiceProvider::class,
    App\Comment\Providers\CommentServiceProvider::class,
    App\Friendship\Providers\FriendshipServiceProvider::class,
    App\MediaLibrary\Providers\MediaLibraryServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
];
