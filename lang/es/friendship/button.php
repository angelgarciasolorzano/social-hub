<?php

declare(strict_types=1);

/**
 * Textos del botón de amistad reutilizable (`modules/friendship/components/FriendshipButton`)
 * y de su diálogo para eliminar amigo. Se comparten en todas las páginas, porque el botón
 * aparece en la búsqueda, en la página de amigos, en el perfil y en el lateral.
 */
return [
    'states' => [
        'add' => 'Agregar amigo',
        'request_sent' => 'Solicitud enviada',
        'accept' => 'Aceptar',
        'decline' => 'Rechazar',
        'friends' => 'Amigos',
    ],

    'menu' => [
        'label' => 'Opciones de amistad con :name',
        'cancel_request' => 'Cancelar solicitud',
        'remove_friend' => 'Eliminar amigo',
    ],

    'remove_dialog' => [
        'title' => '¿Eliminar a :name de tus amigos?',
        'description' => 'Dejarán de ser amigos. Para volver a serlo, uno de los dos tendrá que enviar una nueva solicitud.',
        'confirm' => 'Eliminar amigo',
        'cancel' => 'Mantener como amigo',
    ],

    'processing' => [
        'sending' => 'Enviando la solicitud de amistad a :name',
        'accepting' => 'Aceptando la solicitud de amistad de :name',
        'declining' => 'Rechazando la solicitud de amistad de :name',
        'canceling' => 'Cancelando la solicitud de amistad a :name',
        'removing' => 'Eliminando a :name de tus amigos',
    ],

    'profile' => [
        'friends_count' => '{0} Sin amigos|{1} :count amigo|[2,*] :count amigos',
        'mutual_friends' => '{1} :count amigo en común|[2,*] :count amigos en común',
    ],

    'error' => 'No se pudo completar la acción. Inténtalo de nuevo.',
];
