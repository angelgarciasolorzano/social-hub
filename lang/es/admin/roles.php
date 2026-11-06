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
                'description' => 'Crear, editar, duplicar y eliminar roles personalizados, y cambiar los permisos de un rol y los roles de otros usuarios.',
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
                'label' => 'Editar roles',
                'description' => 'Cambiar los permisos de un rol y el nombre y la descripción de un rol personalizado.',
            ],
            'admin_roles_create' => [
                'label' => 'Crear roles',
                'description' => 'Crear roles personalizados y duplicar los existentes.',
            ],
            'admin_roles_delete' => [
                'label' => 'Eliminar roles',
                'description' => 'Eliminar roles personalizados que ningún usuario tiene.',
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

    'types' => [
        'column' => 'Tipo',
        'filter' => 'Tipo',
        'system' => 'Sistema',
        'custom' => 'Personalizado',
        'system_tooltip' => 'Viene con la aplicación. Sus permisos se pueden editar, pero no se puede renombrar ni eliminar.',
        'custom_tooltip' => 'Creado desde el panel. Se puede renombrar, duplicar y eliminar.',
    ],

    'pages' => [
        'create' => [
            'title' => 'Nuevo rol',
            'breadcrumb' => 'Nuevo',
        ],
        'edit' => [
            'title' => 'Editar :role',
            'breadcrumb' => 'Editar',
        ],
    ],

    'wizard' => [
        'details' => [
            'label' => 'Datos',
            'description' => 'Nombre y propósito',
        ],
        'permissions' => [
            'label' => 'Permisos',
            'description' => 'Qué permite el rol',
        ],
        'review' => [
            'label' => 'Revisión',
            'description' => 'Revisar y confirmar',
        ],
    ],

    'form' => [
        'label' => 'Nombre',
        'label_helper' => 'El nombre que se muestra en el panel. No puede repetirse entre roles.',
        'description' => 'Descripción',
        'description_helper' => 'Para qué sirve este rol, en una frase.',
        'permissions' => 'Permisos',
        'permissions_helper' => 'Lo que puede hacer un usuario con este rol, agrupado por área del panel.',
        'dependencies_note' => 'Al marcar un permiso se marcan los que necesita, y al desmarcarlo se desmarcan los que dependen de él.',
        'start_from' => 'Partir de un rol existente',
        'start_from_helper' => 'Copia los permisos de ese rol al siguiente paso, donde todavía puedes cambiarlos. El rol nuevo no queda vinculado a él.',
        'read_only_details' => 'Los roles de sistema conservan el nombre y la descripción del catálogo. Solo se pueden cambiar sus permisos.',
        'sensitive' => 'Sensible: da control sobre otros administradores, sus accesos o datos exportados.',
        'acknowledge_sensitive' => 'Entiendo que este rol concederá permisos sensibles.',
        'acknowledge_sensitive_required' => 'Confirma que quieres conceder permisos sensibles.',
    ],

    'locked' => [
        'badge' => 'Bloqueado',
        'tooltip' => 'El rol Administrador solo da acceso al panel, así que no se puede editar.',
        'notice' => 'Este rol solo da acceso al panel. Las secciones se dan con otros roles.',
    ],

    'summary' => [
        'heading' => 'Resumen de cambios',
        'grant' => '{1} Concede :count permiso: :list|[0,*] Concede :count permisos: :list',
        'revoke' => '{1} Quita :count permiso: :list|[0,*] Quita :count permisos: :list',
        'none' => 'Los permisos no cambian.',
        'count' => '{0} Sin permisos|{1} :count permiso|[2,*] :count permisos',
        'areas' => 'Áreas: :areas',
        'no_areas' => 'Todavía no cubre ningún área.',
        'sensitive_heading' => 'Permisos sensibles',
        'no_sensitive' => 'Sin permisos sensibles.',
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
        'edit' => [
            'label' => 'Editar',
            'success' => 'Se actualizó :role',
            'unchanged' => 'No cambió nada.',
        ],
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
            'summary_grant' => '{1} Asigna :count rol: :list|[0,*] Asigna :count roles: :list',
            'summary_revoke' => '{1} Quita :count rol: :list|[0,*] Quita :count roles: :list',
            'summary_none' => 'Los roles no cambian.',
        ],
        'create' => [
            'label' => 'Nuevo rol',
            'modal_heading' => 'Crear un rol',
            'submit' => 'Crear rol',
            'success' => 'Se creó el rol :role',
        ],
        'edit_details' => [
            'label' => 'Editar datos',
            'modal_heading' => 'Editar los datos de :role',
            'submit' => 'Guardar datos',
            'success' => 'Se guardaron los datos de :role',
            'unchanged' => 'Los datos no cambiaron',
        ],
        'duplicate' => [
            'label' => 'Duplicar',
            'modal_heading' => 'Duplicar :role',
            'modal_description' => 'La copia es un rol personalizado con los mismos permisos y sin usuarios.',
            'copy_label' => ':role (copia)',
            'submit' => 'Duplicar rol',
            'success' => 'Se creó el rol :role como copia',
        ],
        'delete' => [
            'label' => 'Eliminar',
            'modal_heading' => 'Eliminar :role',
            'modal_description' => 'El rol se elimina para siempre. La eliminación queda en el registro de actividad.',
            'blocked_by_users' => '{1} :count usuario tiene este rol. Quítaselo antes de eliminarlo.|[0,*] :count usuarios tienen este rol. Quítaselo antes de eliminarlo.',
            'submit' => 'Eliminar rol',
            'success' => 'Se eliminó el rol :role',
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
        'role_locked' => 'El rol Administrador solo da acceso al panel y no se puede cambiar.',
        'system_role_protected' => 'Este rol viene con la aplicación, así que no se puede renombrar ni eliminar.',
        'role_has_users' => 'Este rol todavía tiene usuarios, así que no se eliminó. Quítaselo primero.',
        'role_label_taken' => 'Otro rol ya usa este nombre.',
        'permission_dependency_missing' => 'Un permiso seleccionado necesita otro que no está seleccionado, así que no se cambió nada.',
    ],

    'relations' => [
        'users' => [
            'title' => 'Usuarios',
            'name' => 'Nombre',
            'email' => 'Correo',
            'empty' => 'Ningún usuario tiene este rol.',
            'add' => [
                'label' => 'Agregar usuarios',
                'modal_heading' => 'Agregar usuarios a :role',
                'field' => 'Usuarios',
                'submit' => 'Agregar usuarios',
                'success' => '{1} Se agregó :count usuario|[0,*] Se agregaron :count usuarios',
            ],
            'remove' => [
                'label' => 'Quitar',
                'modal_heading' => 'Quitar :role a :name',
                'modal_description' => 'El usuario pierde los permisos de este rol en su siguiente petición. El cambio queda en el registro de actividad.',
                'submit' => 'Quitar',
                'success' => ':name ya no tiene :role',
            ],
            'columns' => [
                'assigned_at' => 'Asignado el',
                'assigned_by' => 'Asignado por',
                'panel_mfa' => 'MFA del panel',
                'last_login_at' => 'Último acceso',
                'other_roles' => 'Otros roles',
            ],
            'not_recorded' => 'Sin registro',
            'mfa' => [
                'configured' => 'Configurado',
                'missing' => 'Sin configurar',
            ],
            'filters' => [
                'status' => 'Estado de la cuenta',
                'panel_mfa' => 'MFA del panel',
                'also_has_role' => 'Tiene además el rol',
                'assigned_between' => 'Asignado entre',
                'inactive_for' => 'Sin acceso en',
                'inactive_option' => 'Los últimos :days días',
                'assigned_from' => 'Asignado desde',
                'assigned_until' => 'Asignado hasta',
                'all_holders' => 'Todos los titulares',
            ],
            'bulk_remove' => [
                'label' => 'Quitar el rol',
                'modal_heading' => 'Quitar :role a los usuarios seleccionados',
                'modal_description' => 'Pierden los permisos de este rol en su siguiente petición. Si a alguno no se le puede quitar, no se cambia a nadie. Cada cambio queda en el registro de actividad.',
                'submit' => 'Quitar el rol',
                'success' => '{0} Nadie tenía ya el rol|{1} :count usuario ya no tiene el rol|[2,*] :count usuarios ya no tienen el rol',
            ],
            'summary' => 'Sin MFA del panel: :without_mfa · Sin acceso en 90 días: :inactive · Suspendidos: :suspended',
        ],
        'history' => [
            'title' => 'Historial',
            'columns' => [
                'change_type' => 'Tipo de cambio',
                'detail' => 'Detalle',
            ],
            'change_types' => [
                'permissions' => 'Permisos',
                'users' => 'Usuarios',
                'lifecycle' => 'Ciclo de vida del rol',
            ],
            'filters' => [
                'change_type' => 'Tipo de cambio',
            ],
            'group' => [
                'day' => 'Día',
            ],
            'detail' => [
                'permission_granted' => 'Agregó el permiso :permission',
                'permission_revoked' => 'Quitó el permiso :permission',
                'role_granted' => 'Asignó el rol a :user',
                'role_revoked' => 'Quitó el rol a :user',
                'bootstrapped' => 'Asignó el rol a :user al configurar el panel',
                'created' => '{0} Creó el rol sin permisos|{1} Creó el rol con :count permiso|[2,*] Creó el rol con :count permisos',
                'updated_label' => 'Cambió el nombre',
                'updated_description' => 'Cambió la descripción',
                'updated_label_and_description' => 'Cambió el nombre y la descripción',
                'updated' => 'Cambió los datos del rol',
                'duplicated' => 'Creado como copia de :role',
                'deleted' => 'Eliminó el rol',
                'unknown' => 'Cambio sin detalles',
            ],
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
                'description' => 'Roles de sistema y personalizados que nadie tiene',
            ],
            'changes' => [
                'label' => 'Cambios de autorización (30 días)',
                'description' => 'Roles asignados o quitados, cambios de permisos y roles creados, editados o eliminados',
            ],
        ],
        'users_per_role' => [
            'heading' => 'Usuarios por rol',
            'description' => 'Cuántos usuarios tienen cada rol, de sistema o personalizado.',
            'dataset' => 'Usuarios',
            'empty_heading' => 'Todavía no hay roles',
            'empty_description' => 'Ejecuta el seeder de autorización de Admin para crear el catálogo.',
        ],
        'types' => [
            'heading' => 'Roles por tipo',
            'description' => 'Los roles de sistema vienen con la aplicación; los personalizados se crean desde el panel.',
            'system' => 'Roles de sistema',
            'custom' => 'Roles personalizados',
            'unused_custom' => 'Roles personalizados sin usuarios',
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
