<?php

declare(strict_types=1);

/**
 * Texts of the Roles section of the admin panel: the catalog of roles and permissions, the resource,
 * its table, detail, actions, relation managers, widgets and permission matrix, and the role pieces
 * shown in the Users section.
 */
return [
    'resource' => [
        'model_label' => 'role',
        'plural_model_label' => 'roles',
        'navigation_label' => 'Roles',
        'navigation_group' => 'Access control',
    ],

    'catalog' => [
        'roles' => [
            'admin' => [
                'label' => 'Administrator',
                'description' => 'Enter the admin panel. Grants no section by itself.',
            ],
            'admin_users_reader' => [
                'label' => 'Users reader',
                'description' => 'List users and open their details.',
            ],
            'admin_users_provisioner' => [
                'label' => 'Users provisioner',
                'description' => 'List users and invite new administrators.',
            ],
            'admin_users_moderator' => [
                'label' => 'Users moderator',
                'description' => 'Suspend and reactivate accounts from the user detail.',
            ],
            'admin_posts_reader' => [
                'label' => 'Posts reader',
                'description' => 'List posts and open their details and comments.',
            ],
            'admin_dashboard_reader' => [
                'label' => 'Dashboard reader',
                'description' => 'View the dashboard and its widgets.',
            ],
            'admin_activity_reader' => [
                'label' => 'Activity reader',
                'description' => 'View the activity log and its details.',
            ],
            'admin_backups_reader' => [
                'label' => 'Backups reader',
                'description' => 'View backups and the health of their destinations.',
            ],
            'admin_backups_operator' => [
                'label' => 'Backups operator',
                'description' => 'View, queue and download database backups.',
            ],
            'admin_announcements_manager' => [
                'label' => 'Announcements manager',
                'description' => 'Create, edit, duplicate, unpublish and delete system announcements.',
            ],
            'admin_announcements_reviewer' => [
                'label' => 'Announcements reviewer',
                'description' => 'View announcements and approve or reject the ones submitted for review.',
            ],
            'admin_roles_reader' => [
                'label' => 'Roles reader',
                'description' => 'View roles, their permissions and the permission matrix.',
            ],
            'admin_roles_manager' => [
                'label' => 'Roles manager',
                'description' => 'Create, edit, duplicate and delete custom roles, change the permissions of a role and the roles of other users.',
            ],
        ],

        'permissions' => [
            'admin_access' => [
                'label' => 'Access the panel',
                'description' => 'Sign in to the admin panel.',
            ],
            'admin_dashboard_view' => [
                'label' => 'View the dashboard',
                'description' => 'Open the dashboard and its widgets.',
            ],
            'admin_users_view_any' => [
                'label' => 'List users',
                'description' => 'Open the list of users.',
            ],
            'admin_users_view' => [
                'label' => 'View a user',
                'description' => 'Open the detail of a user.',
            ],
            'admin_users_create' => [
                'label' => 'Invite administrators',
                'description' => 'Send administrator invitations.',
            ],
            'admin_users_suspend' => [
                'label' => 'Suspend accounts',
                'description' => 'Suspend another active account.',
            ],
            'admin_users_reactivate' => [
                'label' => 'Reactivate accounts',
                'description' => 'Reactivate a suspended account.',
            ],
            'admin_users_roles' => [
                'label' => 'Manage user roles',
                'description' => 'Assign and remove the roles of other users.',
            ],
            'admin_posts_view_any' => [
                'label' => 'List posts',
                'description' => 'Open the list of posts.',
            ],
            'admin_posts_view' => [
                'label' => 'View a post',
                'description' => 'Open the detail of a post and its comments.',
            ],
            'admin_activity_view_any' => [
                'label' => 'List activity',
                'description' => 'Open the activity log.',
            ],
            'admin_activity_view' => [
                'label' => 'View an activity',
                'description' => 'Open the detail of an activity.',
            ],
            'admin_backups_view' => [
                'label' => 'View backups',
                'description' => 'Open the backups page.',
            ],
            'admin_backups_create' => [
                'label' => 'Create backups',
                'description' => 'Queue a database backup.',
            ],
            'admin_backups_download' => [
                'label' => 'Download backups',
                'description' => 'Download a backup after confirming the password.',
            ],
            'admin_announcements_view_any' => [
                'label' => 'List announcements',
                'description' => 'Open the list of system announcements.',
            ],
            'admin_announcements_view' => [
                'label' => 'View an announcement',
                'description' => 'Open the detail and preview of an announcement.',
            ],
            'admin_announcements_review' => [
                'label' => 'Review announcements',
                'description' => 'Approve or reject announcements submitted for review.',
            ],
            'admin_announcements_create' => [
                'label' => 'Create announcements',
                'description' => 'Create and duplicate announcements.',
            ],
            'admin_announcements_update' => [
                'label' => 'Edit announcements',
                'description' => 'Edit and unpublish announcements.',
            ],
            'admin_announcements_delete' => [
                'label' => 'Delete announcements',
                'description' => 'Delete announcements.',
            ],
            'admin_roles_view_any' => [
                'label' => 'List roles',
                'description' => 'Open the list of roles and the permission matrix.',
            ],
            'admin_roles_view' => [
                'label' => 'View a role',
                'description' => 'Open the detail of a role.',
            ],
            'admin_roles_update' => [
                'label' => 'Edit roles',
                'description' => 'Change the permissions of a role and the name and description of a custom role.',
            ],
            'admin_roles_create' => [
                'label' => 'Create roles',
                'description' => 'Create custom roles and duplicate existing ones.',
            ],
            'admin_roles_delete' => [
                'label' => 'Delete roles',
                'description' => 'Delete custom roles that no user holds.',
            ],
        ],

        'areas' => [
            'access' => 'Panel access',
            'dashboard' => 'Dashboard',
            'users' => 'Users',
            'posts' => 'Posts',
            'activity' => 'Activity log',
            'backups' => 'Backups',
            'announcements' => 'Announcements',
            'roles' => 'Roles',
        ],
    ],

    'types' => [
        'column' => 'Type',
        'filter' => 'Type',
        'system' => 'System',
        'custom' => 'Custom',
        'system_tooltip' => 'Comes with the application. Its permissions can be edited, but it cannot be renamed or deleted.',
        'custom_tooltip' => 'Created from the panel. It can be renamed, duplicated and deleted.',
    ],

    'form' => [
        'label' => 'Name',
        'label_helper' => 'The name shown in the panel. It must be unique among roles.',
        'description' => 'Description',
        'description_helper' => 'What this role is for, in one sentence.',
        'permissions' => 'Permissions',
        'permissions_helper' => 'What a user with this role can do, grouped by panel area.',
        'dependencies_note' => 'Checking a permission also checks the ones it needs, and unchecking it unchecks the ones that need it.',
    ],

    'locked' => [
        'badge' => 'Locked',
        'tooltip' => 'The Administrator role only grants access to the panel, so it cannot be edited.',
        'notice' => 'This role only grants access to the panel. Give sections through other roles.',
    ],

    'summary' => [
        'heading' => 'Summary of changes',
        'grant' => '{1} Grants :count permission: :list|[0,*] Grants :count permissions: :list',
        'revoke' => '{1} Revokes :count permission: :list|[0,*] Revokes :count permissions: :list',
        'none' => 'No permission changes.',
    ],

    'table' => [
        'name' => 'Role',
        'description' => 'Description',
        'permissions_count' => 'Permissions',
        'users_count' => 'Users',
        'guard' => 'Guard',
        'not_editable' => 'Not editable',
        'not_editable_tooltip' => 'This role is not part of the catalog, so it cannot be edited from the panel.',
    ],

    'infolist' => [
        'role' => [
            'heading' => 'Role',
            'description' => 'What this role is for and who holds it.',
        ],
        'permissions' => [
            'heading' => 'Permissions',
            'description' => 'What a user with this role can do, grouped by panel area.',
            'empty' => 'This role has no permissions.',
        ],
        'users_count' => 'Users with this role',
    ],

    'actions' => [
        'edit_permissions' => [
            'label' => 'Edit permissions',
            'modal_heading' => 'Edit the permissions of :role',
            'modal_description' => 'Every user with this role gets the selected permissions on their next request. The change is recorded in the activity log.',
            'field' => 'Permissions',
            'submit' => 'Save permissions',
            'success' => '{1} :count permission changed|[0,*] :count permissions changed',
            'unchanged' => 'The permissions did not change',
        ],
        'manage_roles' => [
            'label' => 'Manage roles',
            'modal_heading' => 'Manage the roles of :name',
            'modal_description' => 'The user gets the permissions of the selected roles on their next request. The change is recorded in the activity log.',
            'field' => 'Roles',
            'submit' => 'Save roles',
            'success' => '{1} :count role changed|[0,*] :count roles changed',
            'unchanged' => 'The roles did not change',
            'summary_grant' => '{1} Assigns :count role: :list|[0,*] Assigns :count roles: :list',
            'summary_revoke' => '{1} Removes :count role: :list|[0,*] Removes :count roles: :list',
            'summary_none' => 'The roles do not change.',
        ],
        'create' => [
            'label' => 'New role',
            'modal_heading' => 'Create a role',
            'submit' => 'Create role',
            'success' => 'The role :role was created',
        ],
        'edit_details' => [
            'label' => 'Edit details',
            'modal_heading' => 'Edit the details of :role',
            'submit' => 'Save details',
            'success' => 'The details of :role were saved',
            'unchanged' => 'The details did not change',
        ],
        'duplicate' => [
            'label' => 'Duplicate',
            'modal_heading' => 'Duplicate :role',
            'modal_description' => 'The copy is a custom role with the same permissions and no users.',
            'copy_label' => ':role (copy)',
            'submit' => 'Duplicate role',
            'success' => 'The role :role was created as a copy',
        ],
        'delete' => [
            'label' => 'Delete',
            'modal_heading' => 'Delete :role',
            'modal_description' => 'The role is deleted permanently. The deletion is recorded in the activity log.',
            'blocked_by_users' => '{1} :count user holds this role. Remove it from them before deleting it.|[0,*] :count users hold this role. Remove it from them before deleting it.',
            'submit' => 'Delete role',
            'success' => 'The role :role was deleted',
        ],
        'open_matrix' => [
            'label' => 'Permission matrix',
        ],
        'current_password' => 'Current password',
        'password_incorrect' => 'The password is incorrect.',
        'too_many_attempts' => 'Too many password attempts. Try again in :seconds seconds.',
    ],

    'failures' => [
        'catalog_not_initialized' => 'The authorization catalog is not initialized. Run the Admin authorization seeder first.',
        'role_not_initialized' => 'This role is not initialized. Run the Admin authorization seeder first.',
        'permission_not_initialized' => 'A selected permission is not initialized. Run the Admin authorization seeder first.',
        'last_admin_access' => 'This change would leave no user with access to the panel, so nothing was changed.',
        'actor_would_lose_access' => 'This change would remove your own access to the panel or to this action, so nothing was changed.',
        'role_locked' => 'The Administrator role only grants access to the panel and cannot be changed.',
        'system_role_protected' => 'This role comes with the application, so it cannot be renamed or deleted.',
        'role_has_users' => 'This role still has users, so it was not deleted. Remove it from them first.',
        'role_label_taken' => 'Another role already uses this name.',
        'permission_dependency_missing' => 'A selected permission needs another one that is not selected, so nothing was changed.',
    ],

    'relations' => [
        'users' => [
            'title' => 'Users',
            'name' => 'Name',
            'email' => 'Email',
            'empty' => 'No user has this role.',
            'add' => [
                'label' => 'Add users',
                'modal_heading' => 'Add users to :role',
                'field' => 'Users',
                'submit' => 'Add users',
                'success' => '{1} :count user added|[0,*] :count users added',
            ],
            'remove' => [
                'label' => 'Remove',
                'modal_heading' => 'Remove :role from :name',
                'modal_description' => 'The user loses the permissions of this role on their next request. The change is recorded in the activity log.',
                'submit' => 'Remove',
                'success' => ':name no longer has :role',
            ],
            'columns' => [
                'assigned_at' => 'Assigned on',
                'assigned_by' => 'Assigned by',
                'panel_mfa' => 'Panel MFA',
                'last_login_at' => 'Last sign-in',
                'other_roles' => 'Other roles',
            ],
            'not_recorded' => 'Not recorded',
            'mfa' => [
                'configured' => 'Configured',
                'missing' => 'Not configured',
            ],
            'filters' => [
                'status' => 'Account status',
                'panel_mfa' => 'Panel MFA',
                'also_has_role' => 'Also has the role',
                'assigned_between' => 'Assigned between',
                'inactive_for' => 'No sign-in in',
                'inactive_option' => 'The last :days days',
                'assigned_from' => 'Assigned from',
                'assigned_until' => 'Assigned until',
                'all_holders' => 'All holders',
            ],
            'bulk_remove' => [
                'label' => 'Remove the role',
                'modal_heading' => 'Remove :role from the selected users',
                'modal_description' => 'They lose the permissions of this role on their next request. If one of them cannot be removed, nobody is changed. Each change is recorded in the activity log.',
                'submit' => 'Remove the role',
                'success' => '{0} Nobody had the role anymore|{1} :count user no longer has the role|[2,*] :count users no longer have the role',
            ],
            'summary' => 'Without panel MFA: :without_mfa · No sign-in in 90 days: :inactive · Suspended: :suspended',
        ],
        'history' => [
            'title' => 'History',
            'columns' => [
                'change_type' => 'Type of change',
                'detail' => 'Detail',
            ],
            'change_types' => [
                'permissions' => 'Permissions',
                'users' => 'Users',
                'lifecycle' => 'Role lifecycle',
            ],
            'filters' => [
                'change_type' => 'Type of change',
            ],
            'group' => [
                'day' => 'Day',
            ],
            'detail' => [
                'permission_granted' => 'Added the permission :permission',
                'permission_revoked' => 'Removed the permission :permission',
                'role_granted' => 'Assigned the role to :user',
                'role_revoked' => 'Removed the role from :user',
                'bootstrapped' => 'Assigned the role to :user when setting up the panel',
                'created' => '{0} Created the role without permissions|{1} Created the role with :count permission|[2,*] Created the role with :count permissions',
                'updated_label' => 'Changed the name',
                'updated_description' => 'Changed the description',
                'updated_label_and_description' => 'Changed the name and the description',
                'updated' => 'Changed the role details',
                'duplicated' => 'Created as a copy of :role',
                'deleted' => 'Deleted the role',
                'unknown' => 'Change without details',
            ],
        ],
    ],

    'widgets' => [
        'overview' => [
            'administrators' => [
                'label' => 'Administrators',
                'description' => 'Users who can access the panel',
            ],
            'unused_roles' => [
                'label' => 'Roles without users',
                'description' => 'System and custom roles nobody holds',
            ],
            'changes' => [
                'label' => 'Authorization changes (30 days)',
                'description' => 'Roles assigned or removed, permission changes and roles created, edited or deleted',
            ],
        ],
        'users_per_role' => [
            'heading' => 'Users per role',
            'description' => 'How many users hold each role, system and custom.',
            'dataset' => 'Users',
            'empty_heading' => 'No roles yet',
            'empty_description' => 'Run the Admin authorization seeder to create the catalog.',
        ],
        'types' => [
            'heading' => 'Roles by type',
            'description' => 'System roles come with the application; custom roles are created from the panel.',
            'system' => 'System roles',
            'custom' => 'Custom roles',
            'unused_custom' => 'Custom roles without users',
        ],
    ],

    'matrix' => [
        'title' => 'Permission matrix',
        'description' => 'Which permissions each cataloged role grants. To change a role, open it and edit its permissions.',
        'permission' => 'Permission',
        'granted' => 'Granted',
        'not_granted' => 'Not granted',
    ],

    'users' => [
        'column' => 'Roles',
        'filter' => 'Role',
        'no_roles' => 'No roles',
        'effective_permissions' => [
            'heading' => 'Effective permissions',
            'description' => 'What this user can do in the panel through their roles.',
            'empty' => 'This user has no admin permissions.',
        ],
        'relation' => [
            'title' => 'Roles',
            'role' => 'Role',
            'description' => 'Description',
            'permissions' => 'Permissions',
        ],
    ],

    'quick_links' => [
        'roles' => [
            'label' => 'Roles',
            'description' => 'Review roles, their permissions and the permission matrix.',
        ],
    ],
];
