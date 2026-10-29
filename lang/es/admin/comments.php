<?php

declare(strict_types=1);

/**
 * Textos del detalle independiente de Comments, sus respuestas y widgets de actividad. El contenido sigue siendo redactado por usuarios.
 */
return [
    'resource' => [
        'model_label' => 'comentario',
        'plural_model_label' => 'comentarios',
    ],

    'infolist' => [
        'details' => [
            'heading' => 'Detalles del comentario',
            'description' => 'Autor y datos de publicación de este comentario.',
        ],
        'content' => [
            'heading' => 'Contenido del comentario',
            'description' => 'El mensaje completo de esta conversación.',
        ],
    ],

    'fields' => [
        'author' => 'Autor',
        'created_at' => 'Fecha de creación',
        'message' => 'Mensaje',
        'edited_at' => 'Editado el',
        'replying_to' => 'En respuesta a',
    ],

    'replies' => [
        'table' => [
            'reply' => 'Respuesta',
        ],
        'filters' => [
            'creation_date' => 'Fecha de creación',
        ],
        'actions' => [
            'view' => 'Ver respuesta',
            'modal_heading' => 'Detalles de la respuesta',
            'close' => 'Cerrar',
        ],
        'modal' => [
            'content_heading' => 'Contenido de la respuesta',
            'details_description' => 'Autor y cronología de esta respuesta.',
        ],
    ],

    'activity' => [
        'replies' => 'Respuestas',
        'replies_description' => 'Respuestas a este comentario',
        'likes' => 'Me gusta',
        'likes_description' => 'Me gusta en este comentario',
    ],
];
