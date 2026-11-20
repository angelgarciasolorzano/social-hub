<?php

declare(strict_types=1);

/**
 * Textos de los dos laterales de `HomeLayout` y de su menú móvil: la navegación de la aplicación
 * a la izquierda, el panel reservado a la derecha y el menú de las pantallas chicas. Se comparten
 * en todas las páginas que usan el layout. Las claves `friend_requests` se van con el panel viejo
 * de solicitudes.
 */
return [
    'navigation' => [
        'label' => 'Navegación principal',
        'section' => 'Menú',
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

    'reserved' => [
        'title' => 'Próximamente',
        'description' => 'Este espacio está reservado para funciones nuevas.',
    ],

    'mobile' => [
        'open' => 'Abrir el menú',
        'close' => 'Cerrar el menú',
        'title' => 'Menú',
    ],
];
