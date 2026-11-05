<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;

test('announcement review RBAC values retain their public identifiers', function (): void {
    expect([
        AdminPermission::AdminAnnouncementsReview->value,
        AdminRole::AdminAnnouncementsReviewer->value,
    ])->toBe([
        'admin.announcements.review',
        'admin-announcements-reviewer',
    ]);
});

test('admin RBAC catalogs retain every existing public identifier', function (): void {
    expect(array_map(
        fn (AdminPermission $adminPermission): string => $adminPermission->value,
        AdminPermission::cases(),
    ))->toBe([
        'admin.access',
        'admin.dashboard.view',
        'admin.users.viewAny',
        'admin.users.view',
        'admin.users.create',
        'admin.users.suspend',
        'admin.users.reactivate',
        'admin.users.roles',
        'admin.posts.viewAny',
        'admin.posts.view',
        'admin.activity.viewAny',
        'admin.activity.view',
        'admin.backups.view',
        'admin.backups.create',
        'admin.backups.download',
        'admin.announcements.viewAny',
        'admin.announcements.view',
        'admin.announcements.review',
        'admin.announcements.create',
        'admin.announcements.update',
        'admin.announcements.delete',
        'admin.roles.viewAny',
        'admin.roles.view',
        'admin.roles.update',
        'admin.roles.create',
        'admin.roles.delete',
    ])->and(array_map(
        fn (AdminRole $adminRole): string => $adminRole->value,
        AdminRole::cases(),
    ))->toBe([
        'admin',
        'admin-users-reader',
        'admin-users-provisioner',
        'admin-users-moderator',
        'admin-posts-reader',
        'admin-dashboard-reader',
        'admin-activity-reader',
        'admin-backups-reader',
        'admin-backups-operator',
        'admin-announcements-manager',
        'admin-announcements-reviewer',
        'admin-roles-reader',
        'admin-roles-manager',
    ]);
});
