<?php

declare(strict_types=1);

/**
 * Mensajes de las acciones de amistad y de la búsqueda de personas, que el backend envía a la
 * aplicación de React. Los resultados usan como clave los valores de `FriendshipOperationResult`.
 */
return [
    'results' => [
        'sent' => 'Solicitud de amistad enviada.',
        'accepted' => 'Solicitud de amistad aceptada. Ahora son amigos.',
        'declined' => 'Solicitud de amistad rechazada.',
        'canceled' => 'Solicitud de amistad cancelada.',
        'removed' => 'Amigo eliminado.',
        'already_pending' => 'Ya le enviaste una solicitud de amistad a esta persona.',
        'already_friends' => 'Ya eres amigo de esta persona.',
        'incoming_request_exists' => 'Esta persona ya te envió una solicitud de amistad. Acéptala desde tus solicitudes recibidas.',
        'cooldown_active' => 'Por ahora no puedes enviarle una solicitud de amistad a esta persona. Inténtalo más adelante.',
        'pending_limit_reached' => 'Tienes demasiadas solicitudes de amistad sin respuesta. Cancela algunas antes de enviar nuevas.',
        'receiver_unavailable' => 'No puedes enviarle una solicitud de amistad a esta persona.',
        'no_longer_pending' => 'Esta solicitud o amistad ya cambió. Recarga la página para ver su estado actual.',
    ],

    'validation' => [
        'search_required' => 'Escribe un nombre para buscar.',
        'search_min' => 'Escribe al menos :min caracteres para buscar.',
        'search_max' => 'La búsqueda no puede tener más de :max caracteres.',
    ],
];
