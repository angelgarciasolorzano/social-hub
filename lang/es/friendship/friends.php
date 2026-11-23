<?php

declare(strict_types=1);

/**
 * Textos de la página de amigos (`modules/friendship/Friends`): sus pestañas, el filtro por
 * nombre, el orden, el bloque de solicitudes, las tarjetas, los estados vacíos y de carga, los
 * atajos de teclado y los mensajes de validación.
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
        'placeholder' => 'Filtrar por nombre',
        'clear' => 'Borrar el filtro',
    ],

    'sort' => [
        'label' => 'Ordenar la lista',
        'recent' => 'Más recientes',
        'name' => 'Nombre (A-Z)',
    ],

    'requests' => [
        'title' => 'Solicitudes de amistad',
        'view_all' => '{1} Ver la solicitud|[2,*] Ver las :count solicitudes',
        'label' => 'Últimas solicitudes de amistad que recibiste',
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
        'end_of_list' => 'Llegaste al final de la lista',
    ],

    'row' => [
        'profile_picture_alt' => 'Foto de perfil de :name',
        'friends_since' => 'Amigos desde :date',
        'request_sent_at' => 'Enviada :date',
    ],

    'card' => [
        'profile_picture_alt' => 'Foto de perfil de :name',
        'mutual_friends' => '{0} Ningún amigo en común|{1} :count amigo en común|[2,*] :count amigos en común',
        'view_profile' => 'Ver perfil',
        'more_options' => 'Más opciones para :name',
    ],

    'shortcuts' => [
        'title' => 'Atajos de teclado',
        'description' => 'Muévete por la página de amigos sin el ratón. Los atajos no actúan mientras escribes en un campo.',
        'open' => 'Ver los atajos de teclado',
        'focus_filter' => 'Enfocar el filtro por nombre',
        'friends_tab' => 'Ir a Amigos',
        'received_tab' => 'Ir a Recibidas',
        'sent_tab' => 'Ir a Enviadas',
        'toggle_sort' => 'Cambiar entre más recientes y nombre',
        'show_help' => 'Ver esta ayuda',
        'leave_filter' => 'Salir del filtro para usar los atajos',
        'close' => 'Cerrar',
    ],

    'validation' => [
        'tab_invalid' => 'La lista seleccionada no existe.',
        'sort_invalid' => 'El orden seleccionado no existe.',
        'q_max' => 'El filtro no puede tener más de :max caracteres.',
    ],
];
