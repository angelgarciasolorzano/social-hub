<?php

declare(strict_types=1);

/**
 * Textos de la página de búsqueda de personas (`modules/friendship/Search`) y de sus filas de resultado.
 */
return [
    'page' => [
        'title' => 'Buscar personas',
        'description' => 'Busca personas por su nombre y envíales una solicitud de amistad.',
    ],

    'form' => [
        'label' => 'Buscar personas por nombre',
        'placeholder' => 'Escribe un nombre',
        'clear' => 'Borrar la búsqueda',
    ],

    'states' => [
        'prompt_title' => 'Encuentra a tus amigos',
        'prompt_description' => 'Escribe al menos :min caracteres de un nombre para empezar a buscar.',
        'empty_title' => 'Nadie coincide con «:term»',
        'empty_description' => 'Revisa cómo lo escribiste o prueba con las primeras letras de otro nombre.',
        'loading' => 'Buscando personas',
        'loading_more' => 'Cargando más personas',
    ],

    'result' => [
        'profile_picture_alt' => 'Foto de perfil de :name',
        'mutual_friends' => '{1} :count amigo en común|[2,*] :count amigos en común',
        'view_profile' => 'Ver perfil',
    ],

    'button' => [
        'add' => 'Agregar amigo',
        'sending' => 'Enviando la solicitud de amistad a :name',
        'request_sent' => 'Solicitud enviada',
        'request_received' => 'Te envió una solicitud',
        'friends' => 'Amigos',
        'error' => 'No se pudo enviar la solicitud de amistad. Inténtalo de nuevo.',
    ],
];
