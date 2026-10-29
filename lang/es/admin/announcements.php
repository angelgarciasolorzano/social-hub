<?php

declare(strict_types=1);

/**
 * Textos de la sección de Anuncios del panel de administración: el recurso, formulario, tabla, detalle y vista previa.
 */
return [
    'resource' => [
        'model_label' => 'anuncio',
        'plural_model_label' => 'anuncios',
        'navigation_label' => 'Anuncios',
    ],

    'sections' => [
        'content' => 'Contenido',
        'display' => 'Presentación',
        'button' => 'Botón',
        'history' => 'Historial',
    ],

    'fields' => [
        'title' => 'Título',
        'message' => 'Mensaje',
        'image' => 'Imagen',
        'type' => 'Tipo',
        'audience' => 'Audiencia',
        'display' => 'Se muestra como',
        'is_dismissible' => 'Los usuarios pueden cerrar el anuncio',
        'starts_at' => 'Fecha de inicio',
        'ends_at' => 'Fecha de finalización',
        'button_text' => 'Texto del botón',
        'button_link' => 'Enlace del botón',
        'created_by' => 'Creado por',
        'created_at' => 'Fecha de creación',
        'updated_at' => 'Última actualización',
    ],

    'form' => [
        'has_button' => 'Incluir botón',
        'ends_at_helper' => 'Déjalo vacío para mantenerlo publicado hasta que se desactive.',
        'button_link_helper' => 'Una dirección https:// o una ruta de este sitio, como /setting/two-factor-authentication.',
        'button_link_invalid' => 'El enlace debe comenzar con https:// o ser una ruta de este sitio que comience con /.',
        'overlapping_dialogs' => '{1} Hay :count diálogo más en el mismo período.|[2,*] Hay :count diálogos más en el mismo período.',
    ],

    'options' => [
        'type' => [
            'info' => 'Información',
            'warning' => 'Advertencia',
            'critical' => 'Crítico',
        ],
        'audience' => [
            'all' => 'Todos',
            'authenticated' => 'Usuarios con sesión iniciada',
            'without_two_factor' => 'Usuarios sin autenticación en dos pasos',
        ],
        'display' => [
            'toast' => 'Aviso emergente',
            'dialog' => 'Diálogo',
            'toast_description' => 'Aviso flotante que no interrumpe. Ideal para noticias y avisos rutinarios.',
            'dialog_description' => 'Ventana que el usuario debe cerrar para continuar. Resérvala para avisos urgentes.',
        ],
    ],

    'table' => [
        'starts' => 'Inicio',
        'ends' => 'Finaliza',
        'no_end_date' => 'Sin fecha de finalización',
        'deleted_user' => 'Usuario eliminado',
    ],

    'filters' => [
        'type' => 'Tipo',
        'audience' => 'Audiencia',
        'display' => 'Se muestra como',
    ],

    'tabs' => [
        'scheduled' => 'Programados',
        'active' => 'Activos',
        'expired' => 'Finalizados',
        'active_dialogs' => 'Diálogos activos',
        'all' => 'Todos',
        'active_dialogs_tooltip' => 'Los diálogos interrumpen a los usuarios hasta que los cierran.',
    ],

    'actions' => [
        'preview' => 'Vista previa',
        'preview_heading' => 'Vista previa del anuncio',
        'close' => 'Cerrar',
        'duplicate' => 'Duplicar',
        'duplicate_description' => 'La copia se programa para mañana y no tendrá fecha de finalización, así que no se mostrará hasta que la revises.',
        'duplicated' => 'Anuncio duplicado',
        'unpublish' => 'Desactivar ahora',
        'unpublish_description' => 'Los usuarios dejarán de ver este anuncio de inmediato.',
        'unpublished' => 'Anuncio desactivado',
        'no_longer_running' => 'El anuncio ya no está activo.',
        'administrator_only' => 'Solo un administrador con sesión iniciada puede modificar los anuncios.',
    ],

    'placeholders' => [
        'no_image' => 'Sin imagen',
        'no_end_date' => 'Sin fecha de finalización',
        'no_button' => 'Sin botón',
        'deleted_user' => 'Usuario eliminado',
    ],

    'preview' => [
        'acknowledge' => 'Entendido',
        'close_disabled' => 'Cerrar (desactivado en la vista previa)',
        'shown_as' => 'Se muestra como :display para :audience',
        'dismissible' => 'permanece oculto después de cerrarlo',
        'not_dismissible' => 'vuelve a aparecer en la siguiente sesión después de cerrarlo',
        'final_look_note' => 'La apariencia final en la aplicación podría variar ligeramente.',
    ],
];
