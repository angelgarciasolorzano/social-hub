<?php

declare(strict_types=1);

/**
 * Textos de la sección de Publicaciones del panel. El contenido de publicaciones y comentarios sigue siendo redactado por usuarios.
 */
return [
    'resource' => [
        'model_label' => 'publicación',
        'plural_model_label' => 'publicaciones',
        'navigation_label' => 'Publicaciones',
    ],

    'table' => [
        'image' => 'Imagen',
        'image_alt' => 'Imagen de la publicación',
        'post' => 'Publicación',
        'author' => 'Autor',
        'created_at' => 'Fecha de creación',
    ],

    'filters' => [
        'comment_presence' => 'Presencia de comentarios',
        'all_posts' => 'Todas las publicaciones',
        'has_comments' => 'Con comentarios',
        'no_comments' => 'Sin comentarios',
        'creation_date' => 'Fecha de creación',
    ],

    'detail' => [
        'information' => 'Información de la publicación',
        'information_description' => 'Autor e historial de esta publicación.',
        'author' => 'Autor',
        'created_at' => 'Fecha de creación',
        'updated_at' => 'Última actualización',
        'content' => 'Contenido de la publicación',
        'content_description' => 'El mensaje completo y la imagen compartidos en esta publicación.',
        'message' => 'Mensaje',
        'image' => 'Imagen de la publicación',
        'image_alt' => 'Imagen de la publicación',
    ],

    'comments' => [
        'fields' => [
            'content' => 'Contenido',
            'author' => 'Autor',
            'created_at' => 'Fecha de creación',
        ],
        'filters' => [
            'creation_date' => 'Fecha de creación',
            'author' => 'Autor',
            'reply_presence' => 'Presencia de respuestas',
            'all_comments' => 'Todos los comentarios',
            'has_replies' => 'Con respuestas',
            'no_replies' => 'Sin respuestas',
        ],
    ],

    'widgets' => [
        'overview' => [
            'heading' => 'Resumen de publicaciones',
            'posts' => 'Publicaciones',
            'posts_description' => 'Nuevas publicaciones por día durante los últimos 7 días',
            'comments' => 'Comentarios',
            'comments_description' => 'Nuevos comentarios en publicaciones por día durante los últimos 7 días',
            'likes' => 'Me gusta',
            'likes_description' => 'Nuevos me gusta en publicaciones por día durante los últimos 7 días',
        ],
        'activity' => [
            'comments' => 'Comentarios',
            'comments_description' => 'Nuevos comentarios por día durante los últimos 7 días',
            'replies' => 'Respuestas',
            'replies_description' => 'Nuevas respuestas por día durante los últimos 7 días',
            'likes' => 'Me gusta',
            'likes_description' => 'Nuevos me gusta por día durante los últimos 7 días',
        ],
    ],
];
