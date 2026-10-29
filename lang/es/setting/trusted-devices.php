<?php

declare(strict_types=1);

/**
 * Texts of the trusted devices settings page (`setting/modules/trustedDevice`) outside its dialogs, and
 * the flashes, action names and validation messages of its backend. The dialogs live in
 * `setting/trusted-device-dialogs`; shared buttons such as Cancel live in `setting/common`.
 */
return [
    'page' => [
        'title' => 'Dispositivos de confianza',
        'description' => 'Estos son los dispositivos en los que has iniciado sesión y que has marcado como de confianza.',
        'keyboard_shortcuts' => 'Atajos de teclado',
        'manage' => 'Administrar',
        'info_banner' => 'Los dispositivos de confianza te permiten iniciar sesión más rápido y mantener tu cuenta protegida.',
        'learn_more' => 'Más información sobre dispositivos de confianza',
        'unrecognized_title' => '¿No reconoces algún dispositivo?',
        'unrecognized_description' => 'Revoca su acceso desde el menú del dispositivo. Si detectas actividad sospechosa, revisa la actividad de tu cuenta.',
        'review_activity' => 'Revisar actividad de seguridad',
        'load_failed' => 'No se pudo cargar la información',
        'devices_load_failed' => 'No se pudieron cargar los dispositivos de confianza.',
    ],

    'deferred_errors' => [
        'stats' => 'No se pudieron cargar las estadísticas de tus dispositivos.',
        'summary' => 'No se pudo cargar el resumen de tus dispositivos.',
        'recent_activity' => 'No se pudo cargar la actividad reciente.',
    ],

    'stats' => [
        'total' => [
            'title' => 'Total de dispositivos',
            'description' => 'Dispositivos registrados',
        ],
        'active' => [
            'title' => 'Dispositivos activos',
            'description' => 'Actualmente pueden iniciar sesión',
        ],
        'expiring_soon' => [
            'title' => 'Próximos a expirar',
            'description' => 'En los próximos 7 días',
        ],
        'recently_added' => [
            'title' => 'Agregado recientemente',
            'description' => 'En los últimos 7 días',
        ],
    ],

    'status' => [
        'active' => 'Activo',
        'expired' => 'Expirado',
        'inactive' => 'Inactivo',
        'revoked' => 'Revocado',
    ],

    'values' => [
        'unknown' => 'Desconocido',
        'unknown_device' => 'Dispositivo desconocido',
        'a_device' => 'Un dispositivo',
        'not_available' => 'No disponible',
        'not_applicable' => 'No aplica',
    ],

    'columns' => [
        'device' => 'Dispositivo',
        'last_access' => 'Último acceso',
        'browser_os' => 'Navegador / SO',
        'expires' => 'Expira',
        'status' => 'Estado',
        'actions' => 'Acciones',
    ],

    'table' => [
        'label' => 'Dispositivos de confianza registrados',
        'loading' => 'Cargando dispositivos de confianza',
        'resize_column' => 'Redimensionar columna :column',
        'resize_hint' => 'Mantén presionado y arrastra para cambiar el ancho',
        'column_width' => ':size píxeles',
        'open_actions' => 'Abrir acciones de :device',
        'empty' => 'No tienes dispositivos de confianza registrados.',
        'empty_filtered_title' => 'No se encontraron dispositivos con esos filtros.',
        'empty_filtered_description' => 'Prueba ajustar o limpiar los filtros aplicados.',
    ],

    'toolbar' => [
        'search_label' => 'Buscar dispositivo',
        'search_placeholder' => 'Buscar dispositivo...',
        'clear_search' => 'Limpiar búsqueda',
        'reload' => 'Recargar datos',
        'columns' => 'Columnas',
        'visible_columns' => 'Columnas visibles',
        'reset_columns' => 'Restablecer columnas',
        'filters' => 'Filtros',
        'reset' => 'Restablecer',
        'clear_filters' => 'Limpiar filtros',
        'no_results' => 'Sin resultados',
        'device_type' => 'Tipo de dispositivo',
        'sort_by' => 'Ordenar por',
        'default_sort' => 'Predeterminado',
    ],

    'filters' => [
        'browsers' => [
            'other' => 'Otro',
        ],
        'device_types' => [
            'desktop' => 'Escritorio / Portátil',
            'mobile' => 'Móvil / Tablet',
        ],
        'last_access' => [
            'last_24_hours' => 'Últimas 24 horas',
            'last_7_days' => 'Últimos 7 días',
            'last_30_days' => 'Últimos 30 días',
        ],
    ],

    'chips' => [
        'active_filters' => 'Filtros activos',
        'active_filters_label' => 'Filtros activos:',
        'clear_filters' => 'Limpiar filtros',
        'remove_filter' => 'Quitar filtro: :label',
        'search' => 'Búsqueda: :value',
        'status' => 'Estado: :value',
        'browser_os' => 'Navegador / SO: :value',
        'device_type' => 'Tipo: :value',
        'last_access' => 'Último acceso: :value',
    ],

    'pagination' => [
        'label' => 'Paginación de dispositivos de confianza',
        'page_of' => 'Página :current de :last',
        'rows_per_page' => 'Filas por página',
        'previous' => 'Página anterior',
        'next' => 'Página siguiente',
    ],

    'sort' => [
        'most_recent' => [
            'label' => 'Más recientes',
            'description' => 'Último acceso (más reciente primero)',
        ],
        'oldest' => [
            'label' => 'Más antiguos',
            'description' => 'Último acceso (más antiguo primero)',
        ],
        'name_asc' => [
            'label' => 'Nombre (A - Z)',
            'description' => 'Nombre del dispositivo (A a Z)',
        ],
        'name_desc' => [
            'label' => 'Nombre (Z - A)',
            'description' => 'Nombre del dispositivo (Z a A)',
        ],
        'expiring_soon' => [
            'label' => 'Próximos a expirar',
            'description' => 'Dispositivos que expiran primero',
        ],
    ],

    'info_card' => [
        'expires_on' => 'Expira el',
        'browser' => 'Navegador',
        'system' => 'Sistema',
        'ip_address' => 'Dirección IP',
        'last_access' => 'Último acceso',
        'identification' => 'Así identificamos este dispositivo',
    ],

    'summary_card' => [
        'os_and_browser' => 'Sistema operativo y navegador',
        'last_used' => 'Último uso:',
        'expiration_date' => 'Fecha de expiración:',
    ],

    'summary' => [
        'title' => 'Resumen de dispositivos',
        'description' => 'Así está la seguridad de tus dispositivos',
        'devices' => 'Dispositivos',
        'active' => 'Activos',
        'expiring_soon' => 'Próximos a expirar',
        'inactive' => 'Inactivos',
        'revoked' => 'Revocados',
        'view_details' => 'Ver detalles',
    ],

    'recent_activity' => [
        'title' => 'Actividad reciente',
        'empty_title' => 'Sin actividad reciente registrada.',
        'empty_description' => 'Las acciones que realices sobre tus dispositivos aparecerán aquí.',
        'view_all' => 'Ver toda la actividad',
    ],

    'recommendations_panel' => [
        'title' => 'Recomendaciones',
        'empty_title' => 'No hay recomendaciones disponibles.',
        'empty_description' => 'Las recomendaciones de seguridad aparecerán aquí cuando estén disponibles.',
        'more' => 'Más recomendaciones',
    ],

    'recommendations' => [
        'review_periodically' => [
            'title' => 'Revisa tus dispositivos periódicamente',
            'description' => 'Mantén solo los dispositivos que realmente usas y reconoces. Elimina los que ya no utilices.',
        ],
        'avoid_shared' => [
            'title' => 'No confíes en dispositivos compartidos',
            'description' => 'Marca como confiables solo equipos bajo tu control. Evita computadoras públicas o de uso compartido.',
        ],
        'revoke_unknown' => [
            'title' => 'Revoca accesos que no reconozcas',
            'description' => 'Si ves un dispositivo extraño, revoca su acceso de inmediato para evitar que personas no autorizadas entren a tu cuenta.',
        ],
        'keep_updated' => [
            'title' => 'Mantén tu navegador y sistema al día',
            'description' => 'Las actualizaciones incluyen parches de seguridad que reducen el riesgo de vulnerabilidades conocidas.',
        ],
        'preview' => [
            'trusted' => 'Confiable',
            'revoke' => 'Revocar',
            'desktop' => 'Escritorio',
            'mobile' => 'Móvil',
            'footer_title' => 'Pequeñas acciones, gran diferencia',
            'footer_description' => 'Cada dispositivo que revisas fortalece la seguridad de tu cuenta.',
        ],
    ],

    'shortcuts' => [
        'add_device' => 'Agregar un dispositivo de confianza',
        'activity' => 'Abrir toda la actividad',
        'recommendations' => 'Abrir recomendaciones',
        'summary' => 'Abrir el resumen de dispositivos',
        'revoke_all' => 'Revocar todos los dispositivos',
    ],

    'row_actions' => [
        'menu' => 'Acciones del dispositivo',
        'view' => 'Ver dispositivo',
        'rename' => 'Renombrar dispositivo',
        'renew' => 'Renovar confianza',
        'reactivate' => 'Reactivar',
        'revoke' => 'Revocar dispositivo',
        'force_destroy' => 'Eliminar definitivamente',
    ],

    'header_actions' => [
        'add' => 'Agregar dispositivo',
        'revoke_all' => 'Revocar todos',
    ],

    /*
     * Flash messages of `TrustedDeviceOperationResult::message()`, one per case.
     */
    'results' => [
        'created' => 'Dispositivo agregado correctamente.',
        'already_active' => 'Este dispositivo ya está registrado como de confianza.',
        'already_revoked' => 'Este dispositivo ya está registrado pero fue revocado. Reactívalo desde la lista de dispositivos revocados en lugar de agregarlo nuevamente.',
        'renamed' => 'Dispositivo renombrado correctamente.',
        'renewed' => 'Confianza renovada correctamente.',
        'revoked' => 'Dispositivo de confianza revocado correctamente.',
        'revoked_all' => 'Todos los dispositivos de confianza fueron revocados.',
        'reactivated' => 'Dispositivo reactivado correctamente. Se regeneró el token de confianza por seguridad.',
        'force_deleted' => 'Dispositivo eliminado permanentemente.',
        'two_factor_required' => 'Primero debes activar y confirmar la autenticación de dos factores para administrar dispositivos de confianza.',
    ],

    /*
     * Names of the `TrustedDeviceAction` cases, keyed by their stored value.
     */
    'actions' => [
        'created' => 'Dispositivo agregado',
        'renewed' => 'Confianza renovada',
        'renamed' => 'Dispositivo renombrado',
        'revoked' => 'Dispositivo revocado',
        'revoked_all' => 'Todos los dispositivos revocados',
        'reactivated' => 'Dispositivo reactivado',
    ],

    'validation' => [
        'code_required' => 'Debes ingresar el código de verificación.',
        'code_length' => 'El código debe tener 6 dígitos.',
        'code_digits' => 'El código solo puede contener números.',
        'code_incorrect' => 'El código de verificación es incorrecto.',
        'code_too_long' => 'El código es demasiado largo.',
        'code_format' => 'Ingresa un código de 6 dígitos o un código de recuperación válido.',
        'name_required' => 'El nombre es obligatorio.',
        'name_max' => 'El nombre no puede tener más de :max caracteres.',
        'password_required' => 'Debes confirmar tu contraseña.',
        'password_incorrect' => 'La contraseña proporcionada no es correcta.',
        'terms_required' => 'Debes confirmar que entiendes las consecuencias.',
        'terms_accepted' => 'Debes aceptar los términos para continuar.',
    ],
];
