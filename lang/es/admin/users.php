<?php

declare(strict_types=1);

/**
 * Texts of the Users section of the admin panel: the resource, its table, detail, actions,
 * relation managers and widgets.
 */
return [
    'resource' => [
        'model_label' => 'usuario',
        'plural_model_label' => 'usuarios',
        'navigation_label' => 'Usuarios',
    ],

    'account_status' => [
        'label' => 'Estado de la cuenta',
        'active' => 'Activa',
        'suspended' => 'Suspendida',
    ],

    'fields' => [
        'profile_picture' => 'Foto de perfil',
        'cover_image' => 'Imagen de portada',
        'email_address' => 'Correo electrónico',
        'email_verification' => 'Verificación del correo',
    ],

    'table' => [
        'profile_picture_alt' => 'Foto de perfil de :name',
        'name' => 'Nombre',
        'email' => 'Correo electrónico',
        'email_verified_at' => 'Correo verificado el',
        'created_at' => 'Creado el',
        'last_login_at' => 'Último inicio de sesión',
        'filters' => [
            'registration_date' => 'Fecha de registro',
            'post_presence' => 'Publicaciones',
            'all_users' => 'Todos los usuarios',
            'has_posts' => 'Con publicaciones',
            'no_posts' => 'Sin publicaciones',
        ],
    ],

    'invite' => [
        'label' => 'Invitar administrador',
        'modal_heading' => 'Invitar administrador',
        'submit' => 'Enviar invitación',
        'success' => 'Correo de invitación en cola',
        'email_helper' => 'Busca por nombre o correo. Para invitar a alguien nuevo, escribe su correo completo y elige la opción de invitar.',
        'new_email_option' => 'Invitar al correo nuevo: :email',
    ],

    'infolist' => [
        'profile' => [
            'heading' => 'Perfil',
            'description' => 'Identidad e imágenes de perfil de este usuario.',
        ],
        'full_name' => 'Nombre completo',
        'cover_image_uploaded' => 'Subida',
        'cover_image_not_added' => 'Sin agregar',
        'account_activity' => [
            'heading' => 'Actividad de la cuenta',
            'description' => 'Verificación del correo e historial de inicios de sesión de este usuario.',
        ],
        'verified' => 'Verificado',
        'not_verified' => 'Sin verificar',
        'verified_at' => 'Verificado el',
        'created_at' => 'Cuenta creada',
        'last_sign_in' => 'Último inicio de sesión',
        'never_signed_in' => 'Nunca ha iniciado sesión',
    ],

    'actions' => [
        'suspend' => [
            'label' => 'Suspender cuenta',
            'modal_heading' => 'Suspender cuenta',
            'modal_description' => 'La cuenta se cierra en todos lados, se revocan sus dispositivos de confianza y no puede iniciar sesión hasta que se reactive. Sus datos se conservan.',
            'submit' => 'Suspender',
            'success' => 'Cuenta suspendida',
            'failure' => 'La cuenta ya estaba suspendida; recarga la página para ver su estado actual',
        ],
        'reactivate' => [
            'label' => 'Reactivar cuenta',
            'modal_heading' => 'Reactivar cuenta',
            'modal_description' => 'La cuenta puede volver a iniciar sesión. Los dispositivos de confianza revocados por la suspensión siguen revocados.',
            'submit' => 'Reactivar',
            'success' => 'Cuenta reactivada',
            'failure' => 'La cuenta ya estaba activa; recarga la página para ver su estado actual',
        ],
    ],

    'relations' => [
        'status_history' => [
            'title' => 'Historial de estados',
            'changed_by' => 'Cambiado por',
        ],
        'activity' => [
            'title' => 'Actividad',
        ],
        'posts' => [
            'title' => 'Publicaciones',
            'image' => 'Imagen',
            'image_alt' => 'Imagen de la publicación',
            'post' => 'Publicación',
            'created_at' => 'Creada el',
            'filters' => [
                'comment_presence' => 'Comentarios',
                'all_posts' => 'Todas las publicaciones',
                'has_comments' => 'Con comentarios',
                'no_comments' => 'Sin comentarios',
                'creation_date' => 'Fecha de creación',
            ],
        ],
        'trusted_devices' => [
            'title' => 'Dispositivos de confianza',
            'device' => 'Dispositivo',
            'unknown_device' => 'Dispositivo desconocido',
            'browser_os' => 'Navegador / Sistema',
            'unknown' => 'Desconocido',
            'last_used' => 'Último uso',
            'expires' => 'Vence',
            'statuses' => [
                'active' => 'Activo',
                'expired' => 'Vencido',
                'revoked' => 'Revocado',
            ],
        ],
    ],

    'widgets' => [
        'activity_overview' => [
            'posts' => [
                'label' => 'Publicaciones',
                'description' => 'Publicaciones nuevas por día en los últimos 7 días',
            ],
            'comments' => [
                'label' => 'Comentarios',
                'description' => 'Comentarios nuevos por día en los últimos 7 días',
            ],
            'active_trusted_devices' => [
                'label' => 'Dispositivos de confianza activos',
                'description' => 'Ni revocados ni vencidos',
            ],
            'status_changes' => [
                'label' => 'Cambios de estado (30 días)',
                'description' => 'Suspensiones y reactivaciones de la cuenta',
            ],
        ],
    ],
];
