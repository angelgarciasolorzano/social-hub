<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Auth\Providers\AuthServiceProvider::class,
    App\User\Providers\UserServiceProvider::class,
    App\Post\Providers\PostServiceProvider::class,
    App\Comment\Providers\CommentServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
];
