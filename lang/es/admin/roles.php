<?php

declare(strict_types=1);

/**
 * Texts of the Roles section of the admin panel: the catalog of roles and permissions, the resource,
 * its table, detail, actions, relation managers, widgets and permission matrix, and the role pieces
 * shown in the Users section.
 */
return [
    'resource' => [
        'model_label' => 'rol',
        'plural_model_label' => 'roles',
        'navigation_label' => 'Roles',
        'navigation_group' => 'Control de acceso',
    ],

    'catalog' => [
        'roles' => [
            'admin' => [
                'label' => 'Administrador',
                'description' => 'Entrar al panel de administración. Por sí solo no da acceso a ninguna sección.',
            ],
            'admin_users_reader' => [
                'label' => 'Lector de usuarios',
                'description' => 'Listar usuarios y abrir su detalle.',
            ],
            'admin_users_provisioner' => [
                'label' => 'Invitador de usuarios',
                'description' => 'Listar usuarios e invitar a nuevos administradores.',
            ],
            'admin_users_moderator' => [
                'label' => 'Moderador de usuarios',
                'description' => 'Suspender y reactivar cuentas desde el detalle del usuario.',
            ],
            'admin_posts_reader' => [
                'label' => 'Lector de publicaciones',
                'description' => 'Listar publicaciones y abrir su detalle y sus comentarios.',
            ],
            'admin_dashboard_reader' => [
                'label' => 'Lector del panel principal',
                'description' => 'Ver el panel principal y sus widgets.',
            ],
            'admin_activity_reader' => [
                'label' => 'Lector de actividad',
                'description' => 'Ver el registro de actividad y su detalle.',
            ],
            'admin_backups_reader' => [
                'label' => 'Lector de respaldos',
                'description' => 'Ver los respaldos y el estado de sus destinos.',
            ],
            'admin_backups_operator' => [
                'label' => 'Operador de respaldos',
                'description' => 'Ver, poner en cola y descargar respaldos de la base de datos.',
            ],
            'admin_announcements_manager' => [
                'label' => 'Gestor de anuncios',
                'description' => 'Crear, editar, duplicar, despublicar y eliminar anuncios del sistema.',
            ],
            'admin_announcements_reviewer' => [
                'label' => 'Revisor de anuncios',
                'description' => 'Ver anuncios y aprobar o rechazar los enviados a revisión.',
            ],
            'admin_roles_reader' => [
                'label' => 'Lector de roles',
                'description' => 'Ver los roles, sus permisos y la matriz de permisos.',
            ],
            'admin_roles_manager' => [
                'label' => 'Gestor de roles',
                'description' => 'Cambiar los permisos de un rol y los roles de otros usuarios.',
            ],
        ],

        'permissions' => [
            'admin_access' => [
                'label' => 'Acceder al panel',
                'description' => 'Iniciar sesión en el panel de administración.',
            ],
            'admin_dashboard_view' => [
                'label' => 'Ver el panel principal',
                'description' => 'Abrir el panel principal y sus widgets.',
            ],
            'admin_users_view_any' => [
                'label' => 'Listar usuarios',
                'description' => 'Abrir la lista de usuarios.',
            ],
            'admin_users_view' => [
                'label' => 'Ver un usuario',
                'description' => 'Abrir el detalle de un usuario.',
            ],
            'admin_users_create' => [
                'label' => 'Invitar administradores',
                'description' => 'Enviar invitaciones de administrador.',
            ],
            'admin_users_suspend' => [
                'label' => 'Suspender cuentas',
                'description' => 'Suspender otra cuenta activa.',
            ],
            'admin_users_reactivate' => [
                'label' => 'Reactivar cuentas',
                'description' => 'Reactivar una cuenta suspendida.',
            ],
            'admin_users_roles' => [
                'label' => 'Gestionar roles de usuarios',
                'description' => 'Asignar y quitar los roles de otros usuarios.',
            ],
            'admin_posts_view_any' => [
                'label' => 'Listar publicaciones',
                'description' => 'Abrir la lista de publicaciones.',
            ],
            'admin_posts_view' => [
                'label' => 'Ver una publicación',
                'description' => 'Abrir el detalle de una publicación y sus comentarios.',
            ],
            'admin_activity_view_any' => [
                'label' => 'Listar actividad',
                'description' => 'Abrir el registro de actividad.',
            ],
            'admin_activity_view' => [
                'label' => 'Ver una actividad',
                'description' => 'Abrir el detalle de una actividad.',
            ],
            'admin_backups_view' => [
                'label' => 'Ver respaldos',
                'description' => 'Abrir la página de respaldos.',
            ],
            'admin_backups_create' => [
                'label' => 'Crear respaldos',
                'description' => 'Poner en cola un respaldo de la base de datos.',
            ],
            'admin_backups_download' => [
                'label' => 'Descargar respaldos',
                'description' => 'Descargar un respaldo después de confirmar la contraseña.',
            ],
            'admin_announcements_view_any' => [
                'label' => 'Listar anuncios',
                'description' => 'Abrir la lista de anuncios del sistema.',
            ],
            'admin_announcements_view' => [
                'label' => 'Ver un anuncio',
                'description' => 'Abrir el detalle y la vista previa de un anuncio.',
            ],
            'admin_announcements_review' => [
                'label' => 'Revisar anuncios',
                'description' => 'Aprobar o rechazar los anuncios enviados a revisión.',
            ],
            'admin_announcements_create' => [
                'label' => 'Crear anuncios',
                'description' => 'Crear y duplicar anuncios.',
            ],
            'admin_announcements_update' => [
                'label' => 'Editar anuncios',
                'description' => 'Editar y despublicar anuncios.',
            ],
            'admin_announcements_delete' => [
                'label' => 'Eliminar anuncios',
                'description' => 'Eliminar anuncios.',
            ],
            'admin_roles_view_any' => [
                'label' => 'Listar roles',
                'description' => 'Abrir la lista de roles y la matriz de permisos.',
            ],
            'admin_roles_view' => [
                'label' => 'Ver un rol',
                'description' => 'Abrir el detalle de un rol.',
            ],
            'admin_roles_update' => [
                'label' => 'Editar permisos de roles',
                'description' => 'Conceder y quitar los permisos de un rol del catálogo.',
            ],
        ],

        'areas' => [
            'access' => 'Acceso al panel',
            'dashboard' => 'Panel principal',
            'users' => 'Usuarios',
            'posts' => 'Publicaciones',
            'activity' => 'Registro de actividad',
            'backups' => 'Respaldos',
            'announcements' => 'Anuncios',
            'roles' => 'Roles',
        ],
    ],

    'table' => [
        'name' => 'Rol',
        'description' => 'Descripción',
        'permissions_count' => 'Permisos',
        'users_count' => 'Usuarios',
        'guard' => 'Guard',
        'not_editable' => 'No editable',
        'not_editable_tooltip' => 'Este rol no forma parte del catálogo, así que no se puede editar desde el panel.',
    ],

    'infolist' => [
        'role' => [
            'heading' => 'Rol',
            'description' => 'Para qué sirve este rol y quién lo tiene.',
        ],
        'permissions' => [
            'heading' => 'Permisos',
            'description' => 'Lo que puede hacer un usuario con este rol, agrupado por área del panel.',
            'empty' => 'Este rol no tiene permisos.',
        ],
        'users_count' => 'Usuarios con este rol',
    ],

    'actions' => [
        'edit_permissions' => [
            'label' => 'Editar permisos',
            'modal_heading' => 'Editar los permisos de :role',
            'modal_description' => 'Cada usuario con este rol recibe los permisos seleccionados en su siguiente petición. El cambio queda en el registro de actividad.',
            'field' => 'Permisos',
            'submit' => 'Guardar permisos',
            'success' => '{1} Se cambió :count permiso|[0,*] Se cambiaron :count permisos',
            'unchanged' => 'Los permisos no cambiaron',
        ],
        'manage_roles' => [
            'label' => 'Gestionar roles',
            'modal_heading' => 'Gestionar los roles de :name',
            'modal_description' => 'El usuario recibe los permisos de los roles seleccionados en su siguiente petición. El cambio queda en el registro de actividad.',
            'field' => 'Roles',
            'submit' => 'Guardar roles',
            'success' => '{1} Se cambió :count rol|[0,*] Se cambiaron :count roles',
            'unchanged' => 'Los roles no cambiaron',
        ],
        'open_matrix' => [
            'label' => 'Matriz de permisos',
        ],
        'current_password' => 'Contraseña actual',
        'password_incorrect' => 'La contraseña es incorrecta.',
        'too_many_attempts' => 'Demasiados intentos de contraseña. Vuelve a intentarlo en :seconds segundos.',
    ],

    'failures' => [
        'catalog_not_initialized' => 'El catálogo de autorización no está inicializado. Ejecuta primero el seeder de autorización de Admin.',
        'role_not_initialized' => 'Este rol no está inicializado. Ejecuta primero el seeder de autorización de Admin.',
        'permission_not_initialized' => 'Un permiso seleccionado no está inicializado. Ejecuta primero el seeder de autorización de Admin.',
        'last_admin_access' => 'Este cambio dejaría el panel sin ningún usuario con acceso, así que no se cambió nada.',
        'actor_would_lose_access' => 'Este cambio te quitaría el acceso al panel o a esta acción, así que no se cambió nada.',
    ],

    'relations' => [
        'users' => [
            'title' => 'Usuarios',
            'name' => 'Nombre',
            'email' => 'Correo',
            'empty' => 'Ningún usuario tiene este rol.',
        ],
        'history' => [
            'title' => 'Historial',
        ],
    ],

    'widgets' => [
        'overview' => [
            'administrators' => [
                'label' => 'Administradores',
                'description' => 'Usuarios que pueden entrar al panel',
            ],
            'unused_roles' => [
                'label' => 'Roles sin usuarios',
                'description' => 'Roles del catálogo que nadie tiene',
            ],
            'changes' => [
                'label' => 'Cambios de autorización (30 días)',
                'description' => 'Roles y permisos concedidos o quitados',
            ],
        ],
        'users_per_role' => [
            'heading' => 'Usuarios por rol',
            'description' => 'Cuántos usuarios tienen cada rol del catálogo.',
            'dataset' => 'Usuarios',
            'empty_heading' => 'Todavía no hay roles',
            'empty_description' => 'Ejecuta el seeder de autorización de Admin para crear el catálogo.',
        ],
    ],

    'matrix' => [
        'title' => 'Matriz de permisos',
        'description' => 'Qué permisos concede cada rol del catálogo. Para cambiar un rol, ábrelo y edita sus permisos.',
        'permission' => 'Permiso',
        'granted' => 'Concedido',
        'not_granted' => 'No concedido',
    ],

    'users' => [
        'column' => 'Roles',
        'filter' => 'Rol',
        'no_roles' => 'Sin roles',
        'effective_permissions' => [
            'heading' => 'Permisos efectivos',
            'description' => 'Lo que este usuario puede hacer en el panel a través de sus roles.',
            'empty' => 'Este usuario no tiene permisos de administración.',
        ],
        'relation' => [
            'title' => 'Roles',
            'role' => 'Rol',
            'description' => 'Descripción',
            'permissions' => 'Permisos',
        ],
    ],

    'quick_links' => [
        'roles' => [
            'label' => 'Roles',
            'description' => 'Revisa los roles, sus permisos y la matriz de permisos.',
        ],
    ],
];
