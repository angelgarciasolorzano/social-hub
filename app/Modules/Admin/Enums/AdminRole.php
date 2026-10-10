<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

enum AdminRole: string
{
    case Admin = 'admin';

    case UsersReader = 'users-reader';
}
