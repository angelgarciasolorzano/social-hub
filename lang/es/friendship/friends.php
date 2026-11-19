<?php

declare(strict_types=1);

/**
 * Textos de la página de amigos (`modules/friendship/Friends`): sus pestañas, el filtro por
 * nombre, los estados vacíos y de carga, las filas de la lista y los mensajes de validación.
 */
return [
    'page' => [
        'title' => 'Amigos',
        'description' => 'Tus amigos y las solicitudes de amistad que enviaste o recibiste.',
    ],

    'tabs' => [
        'label' => 'Listas de amigos',
        'friends' => 'Amigos',
        'received' => 'Recibidas',
        'sent' => 'Enviadas',
        'friends_count' => '{0} Ningún amigo|{1} :count amigo|[2,*] :count amigos',
        'received_count' => '{0} Ninguna solicitud recibida|{1} :count solicitud recibida|[2,*] :count solicitudes recibidas',
        'sent_count' => '{0} Ninguna solicitud enviada|{1} :count solicitud enviada|[2,*] :count solicitudes enviadas',
    ],

    'filter' => [
        'label' => 'Filtrar por nombre',
        'placeholder' => 'Escribe un nombre',
        'clear' => 'Borrar el filtro',
    ],

    'states' => [
        'friends_empty_title' => 'Todavía no tienes amigos',
        'friends_empty_description' => 'Busca personas y envíales una solicitud de amistad.',
        'received_empty_title' => 'No tienes solicitudes pendientes',
        'received_empty_description' => 'Cuando alguien te envíe una solicitud de amistad, aparecerá aquí.',
        'sent_empty_title' => 'No tienes solicitudes enviadas',
        'sent_empty_description' => 'Aquí aparecerán las solicitudes de amistad que envíes y que sigan esperando respuesta.',
        'filtered_empty_title' => 'Nadie coincide con «:term»',
        'filtered_empty_description' => 'Revisa cómo lo escribiste o prueba con las primeras letras de otro nombre.',
        'find_people' => 'Buscar personas',
        'loading' => 'Cargando la lista',
        'loading_more' => 'Cargando más personas',
    ],

    'row' => [
        'profile_picture_alt' => 'Foto de perfil de :name',
        'friends_since' => 'Amigos desde :date',
        'request_sent_at' => 'Enviada :date',
    ],

    'validation' => [
        'tab_invalid' => 'La lista seleccionada no existe.',
        'q_max' => 'El filtro no puede tener más de :max caracteres.',
    ],
];
