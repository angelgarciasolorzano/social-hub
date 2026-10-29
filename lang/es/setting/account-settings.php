<?php

declare(strict_types=1);

/**
 * Texts of the Profile settings page (`setting/modules/accountSettings`) and of the flashes its
 * actions answer with. Shared buttons such as Cancel live in `setting/common`.
 */
return [
    'page_title' => 'Configuración de la cuenta',

    'overview' => [
        'title' => 'Información de tu perfil',
        'description' => 'Administra la información personal asociada a tu cuenta.',
        'hint' => 'Mantén tus datos actualizados para garantizar una mejor experiencia.',
        'manage_title' => 'Administra tus datos',
        'manage_description' => 'Actualiza la información personal disponible y consulta cómo se muestra tu perfil público.',
    ],

    'privacy_principles' => [
        'account_data' => [
            'title' => 'Datos de tu cuenta',
            'description' => 'Consulta y actualiza la información personal asociada a tu cuenta.',
        ],
        'public_profile' => [
            'title' => 'Perfil público',
            'description' => 'Consulta cómo se muestra la información de tu perfil público.',
        ],
        'your_choice' => [
            'title' => 'Tú decides',
            'description' => 'Puedes actualizar tus datos o eliminar tu cuenta desde las opciones disponibles.',
        ],
        'account_options' => [
            'title' => 'Opciones de cuenta',
            'description' => 'Revisa las acciones disponibles para administrar tu cuenta.',
        ],
    ],

    'communication' => [
        'title' => 'Preferencias de comunicación',
        'description' => 'Ejemplo visual de los tipos de comunicación disponibles en la plataforma.',
        'channels' => [
            'email' => [
                'title' => 'Correos electrónicos',
                'description' => 'Recibe actualizaciones importantes y noticias.',
            ],
            'system' => [
                'title' => 'Notificaciones del sistema',
                'description' => 'Alertas y mensajes dentro de la plataforma.',
            ],
            'news' => [
                'title' => 'Novedades y consejos',
                'description' => 'Recibe tips, mejoras y novedades de productos.',
            ],
        ],
    ],

    'profile_form' => [
        'title' => 'Información personal',
        'description' => 'Actualiza los datos personales que forman parte de tu perfil.',
        'name' => 'Nombre (obligatorio)',
        'phone' => 'Teléfono (opcional)',
        'phone_placeholder' => 'Aquí escribe tu teléfono, Ej: +56 987654321',
        'email' => 'Correo electrónico',
        'email_help' => 'El correo se muestra como referencia y no se puede editar aquí.',
        'locale' => 'Idioma preferido (obligatorio)',
        'locale_help' => 'El idioma preferido se guarda en tu cuenta.',
        'biography' => 'Biografía (opcional)',
        'biography_help' => 'Máximo :max caracteres.',
        'biography_count' => ':count / :max caracteres',
        'save' => 'Guardar cambios',
        'saving' => 'Guardando cambios…',
        'saved' => 'Cambios guardados correctamente.',
    ],

    /*
     * Each language is named in its own language, so both catalogs share these texts.
     */
    'locales' => [
        'en' => 'English',
        'es' => 'Español',
    ],

    'summary' => [
        'title' => 'Resumen de la cuenta',
        'description' => 'Información de tu perfil.',
        'view_activity' => 'Ver actividad',
        'user' => 'Usuario',
        'email' => 'Correo electrónico',
        'status' => 'Estado de la cuenta',
        'member_since' => 'Miembro desde',
        'last_login' => 'Último acceso',
    ],

    'status' => [
        'active' => 'Activa',
        'suspended' => 'Suspendida',
    ],

    'actions' => [
        'public_profile' => [
            'title' => 'Perfil público',
            'description' => 'Revisa cómo se muestra tu perfil público.',
            'link' => 'Ir a mi perfil público',
        ],
        'password' => [
            'title' => 'Cambiar contraseña',
            'description' => 'Elige una contraseña segura que no uses en otros sitios.',
        ],
        'delete' => [
            'title' => 'Eliminar cuenta',
            'description' => 'Esta acción es permanente y perderás el acceso a tu cuenta.',
        ],
    ],

    'password_dialog' => [
        'trigger' => 'Cambiar contraseña',
        'title' => 'Cambiar contraseña',
        'description' => 'Actualiza tu contraseña para mantener segura tu cuenta.',
        'revocation_title' => 'Se revocará la confianza de tus dispositivos',
        'revocation_description' => 'Al cambiar tu contraseña, se revocará la confianza de todos los dispositivos asociados a tu cuenta. Si tienes activada la autenticación en dos pasos, tendrás que verificarte de nuevo la próxima vez que inicies sesión desde esos dispositivos. Las sesiones que ya estén abiertas seguirán activas.',
        'current_password' => 'Contraseña actual',
        'new_password' => 'Nueva contraseña',
        'password_confirmation' => 'Confirmar contraseña',
        'submit' => 'Guardar contraseña',
        'submitting' => 'Actualizando...',
    ],

    'delete_dialog' => [
        'trigger' => 'Eliminar mi cuenta',
        'title' => 'Eliminar mi cuenta',
        'description' => 'Para continuar, confirma la eliminación con tu contraseña actual.',
        'warning_title' => 'Esta acción es permanente',
        'warning_description' => 'Se eliminará permanentemente tu cuenta y la información de perfil guardada en ella. Se cerrará tu sesión y ya no podrás iniciar sesión con estas credenciales; para volver a usar la plataforma tendrás que registrarte de nuevo. Esta acción no se puede deshacer.',
        'current_password' => 'Contraseña actual',
        'submit' => 'Eliminar cuenta',
        'submitting' => 'Eliminando...',
    ],

    'messages' => [
        'profile_updated' => 'Datos actualizados correctamente.',
        'password_updated' => 'Contraseña actualizada correctamente',
    ],
];
