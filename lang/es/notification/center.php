<?php

declare(strict_types=1);

/**
 * Textos del centro de notificaciones y resúmenes localizados de notificaciones.
 */
return [
    'page' => [
        'title' => 'Notificaciones',
        'description' => 'Mantente al día con los comentarios, las respuestas y los Me gusta en tu contenido.',
    ],

    'bell' => [
        'open' => 'Abrir notificaciones',
        'open_unread' => '{0}Abrir notificaciones|{1}Abrir notificaciones, :count notificación sin leer|[2,*]Abrir notificaciones, :count notificaciones sin leer',
        'unread_count' => '{0}No tienes notificaciones sin leer|{1}:count notificación sin leer|[2,*]:count notificaciones sin leer',
    ],

    'dropdown' => [
        'title' => 'Notificaciones recientes',
        'view_all' => 'Ver todas las notificaciones',
        'empty_title' => 'Estás al día',
        'empty_description' => 'Aquí aparecerán los nuevos comentarios, respuestas y Me gusta.',
    ],

    'history' => [
        'all' => 'Todas',
        'description' => 'Revisa los comentarios, las respuestas y los Me gusta relacionados con tu contenido.',
        'empty_title' => 'Aún no tienes notificaciones',
        'empty_description' => 'Cuando alguien comente, responda o indique que le gusta tu contenido, lo verás aquí.',
        'empty_unread_title' => 'No tienes notificaciones sin leer',
        'empty_unread_description' => 'Ya leíste todas tus notificaciones.',
        'loading' => 'Cargando notificaciones',
        'load_more' => 'Cargar más notificaciones',
        'list_label' => 'Tus notificaciones',
        'unread' => 'Sin leer',
    ],

    'actions' => [
        'mark_all_read' => 'Marcar todas como leídas',
        'mark_read' => 'Marcar como leída',
        'open_post' => 'Abrir publicación relacionada',
        'open_comment' => 'Abrir comentario relacionado',
        'view_all' => 'Ver todas las notificaciones',
    ],

    'status' => [
        'unread' => 'Sin leer',
        'read' => 'Leída',
        'marking_read' => 'Marcando como leída…',
    ],

    'toasts' => [
        'new_notification' => 'Nueva notificación',
        'marked_read' => 'Notificación marcada como leída.',
        'all_marked_read' => 'Todas las notificaciones se marcaron como leídas.',
    ],

    'target' => [
        'post_unavailable' => 'Esta publicación ya no está disponible.',
        'comment_unavailable' => 'Este comentario ya no está disponible.',
        'content_unavailable' => 'El contenido original ya no está disponible.',
    ],

    'summaries' => [
        'comment_on_post' => ':actor comentó en tu publicación',
        'reply_to_comment' => ':actor respondió a tu comentario',
        'liked_post' => '{0}A nadie le gustó tu publicación|{1}A :actor le gustó tu publicación|{2}A :actor y a otra persona les gustó tu publicación|[3,*]A :actor y a :others personas más les gustó tu publicación',
        'liked_comment' => '{0}A nadie le gustó tu comentario|{1}A :actor le gustó tu comentario|{2}A :actor y a otra persona les gustó tu comentario|[3,*]A :actor y a :others personas más les gustó tu comentario',
    ],
];
