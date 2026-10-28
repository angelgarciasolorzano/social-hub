<?php

declare(strict_types=1);

/**
 * Texts of the activity log table, how its parties are described, and the readable names of every
 * cataloged event (keyed by its stored value, `log.action`) and log.
 */
return [
    'table' => [
        'log' => 'Registro',
        'event' => 'Evento',
        'performed_by' => 'Realizado por',
        'subject' => 'Sobre',
    ],

    'parties' => [
        'deleted_causer' => ':type #:id (ya no existe)',
        'subject' => ':type #:id',
        'deleted_subject' => ':type #:id (ya no existe)',
        'types' => [
            'user' => 'Usuario',
            'post' => 'Publicación',
            'like' => 'Me gusta',
            'comment' => 'Comentario',
            'admin_invitation' => 'Invitación de administrador',
            'trusted_device_event' => 'Evento de dispositivo de confianza',
            'account_status_change' => 'Cambio de estado de la cuenta',
            'announcement' => 'Anuncio',
            'role' => 'Rol',
        ],
    ],

    'logs' => [
        'auth' => 'Autenticación',
        'content' => 'Contenido',
        'likes' => 'Me gusta',
        'trusted_devices' => 'Dispositivos de confianza',
        'account' => 'Cuenta',
        'admin' => 'Administración',
    ],

    'events' => [
        'auth' => [
            'login' => 'Inicio de sesión',
            'logout' => 'Cierre de sesión',
            'login_failed' => 'Inicio de sesión fallido',
            'password_updated' => 'Contraseña actualizada',
            'password_reset' => 'Contraseña restablecida',
            'two_factor_confirmed' => 'Verificación en dos pasos activada',
            'two_factor_disabled' => 'Verificación en dos pasos desactivada',
            'recovery_codes_regenerated' => 'Códigos de recuperación regenerados',
        ],
        'content' => [
            'post_created' => 'Publicación creada',
            'comment_created' => 'Comentario creado',
            'comment_replied' => 'Respuesta a un comentario',
            'comment_updated' => 'Comentario editado',
            'comment_deleted' => 'Comentario eliminado',
        ],
        'likes' => [
            'added' => 'Me gusta agregado',
            'removed' => 'Me gusta quitado',
        ],
        'trusted_devices' => [
            'created' => 'Dispositivo de confianza agregado',
            'renewed' => 'Dispositivo de confianza renovado',
            'renamed' => 'Dispositivo de confianza renombrado',
            'revoked' => 'Dispositivo de confianza revocado',
            'revoked_all' => 'Todos los dispositivos de confianza revocados',
            'reactivated' => 'Dispositivo de confianza reactivado',
        ],
        'account' => [
            'updated' => 'Cuenta actualizada',
            'image_updated' => 'Imagen actualizada',
            'deleted' => 'Cuenta eliminada',
        ],
        'admin' => [
            'role_granted' => 'Rol asignado',
            'role_revoked' => 'Rol retirado',
            'role_permission_granted' => 'Permiso agregado al rol',
            'role_permission_revoked' => 'Permiso quitado del rol',
            'bootstrapped' => 'Primer administrador creado',
            'invitation_issued' => 'Invitación enviada',
            'invitation_accepted' => 'Invitación aceptada',
            'backup_created' => 'Respaldo creado',
            'backup_downloaded' => 'Respaldo descargado',
            'account_suspended' => 'Cuenta suspendida',
            'account_reactivated' => 'Cuenta reactivada',
            'announcement_created' => 'Anuncio creado',
            'announcement_updated' => 'Anuncio editado',
            'announcement_unpublished' => 'Anuncio despublicado',
            'announcement_duplicated' => 'Anuncio duplicado',
            'announcement_deleted' => 'Anuncio eliminado',
        ],
    ],
];
