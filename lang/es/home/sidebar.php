<?php

declare(strict_types=1);

/**
 * Textos de los dos laterales de `HomeLayout`: la navegación de la aplicación a la izquierda y
 * el panel de solicitudes de amistad recibidas a la derecha. Se comparten en todas las páginas,
 * porque los dos laterales aparecen en todas las páginas que usan el layout.
 */
return [
    'navigation' => [
        'label' => 'Navegación principal',
        'home' => 'Inicio',
        'search' => 'Buscar personas',
        'friends' => 'Amigos',
        'notifications' => 'Notificaciones',
        'profile' => 'Mi perfil',
        'pending_requests_count' => '{0} Ninguna solicitud de amistad pendiente|{1} :count solicitud de amistad pendiente|[2,*] :count solicitudes de amistad pendientes',
        'unread_notifications_count' => '{0} Ninguna notificación sin leer|{1} :count notificación sin leer|[2,*] :count notificaciones sin leer',
        'loading_count' => 'Cargando el contador',
    ],

    'friend_requests' => [
        'title' => 'Solicitudes de amistad',
        'empty_title' => 'No tienes solicitudes pendientes',
        'empty_description' => 'Cuando alguien te envíe una solicitud de amistad, aparecerá aquí.',
        'view_all' => 'Ver todas las solicitudes',
        'loading' => 'Cargando las solicitudes de amistad',
        'profile_picture_alt' => 'Foto de perfil de :name',
    ],
];
