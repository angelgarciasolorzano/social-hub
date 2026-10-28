<?php

declare(strict_types=1);

/**
 * Texts of the Backups page of the admin panel: the page, its actions, table, destination and
 * retention details, summary widget and download errors.
 */
return [
    'page' => [
        'title' => 'Gestión de respaldos',
        'navigation_group' => 'Administración',
        'navigation_label' => 'Respaldos',
    ],

    'disabled' => 'Desactivado',

    'actions' => [
        'create' => [
            'label' => 'Crear respaldo',
            'modal_heading' => '¿Crear un respaldo de la base de datos?',
            'modal_description' => 'El respaldo se pondrá en cola y aparecerá en esta lista cuando la cola termine de procesarlo.',
            'submit' => 'Poner en cola',
            'success' => 'Respaldo de la base de datos en cola',
        ],
        'download' => [
            'label' => 'Descargar',
            'current_password' => 'Contraseña actual',
            'modal_heading' => 'Confirma tu contraseña para descargar este respaldo',
            'submit' => 'Descargar respaldo',
        ],
    ],

    'errors' => [
        'backup_unavailable' => 'El respaldo seleccionado ya no está disponible.',
        'password_required' => 'La contraseña actual es obligatoria.',
        'password_incorrect' => 'La contraseña es incorrecta.',
        'too_many_attempts' => 'Demasiados intentos de contraseña. Vuelve a intentarlo en :seconds segundos.',
    ],

    'table' => [
        'heading' => 'Respaldos',
        'archive' => 'Archivo',
        'created' => 'Creado',
        'size' => 'Tamaño',
        'availability' => 'Disponibilidad',
        'available' => 'Disponible',
        'missing' => 'Faltante',
        'empty_heading' => 'Aún no hay respaldos',
        'empty_description' => 'Un respaldo de la base de datos aparecerá aquí después de la primera ejecución.',
    ],

    'destination' => [
        'heading' => 'Destino de los respaldos',
        'description' => 'Almacenamiento, disponibilidad y protección de los archivos de respaldo generados.',
        'health' => 'Estado',
        'health_details' => 'Detalle del estado',
        'failed_checks' => 'Comprobaciones fallidas',
        'provider' => 'Proveedor de almacenamiento',
        'bucket' => 'Bucket',
        'prefix' => 'Prefijo de los respaldos',
        'region' => 'Región',
        'access' => 'Acceso',
        'archive_encryption' => 'Cifrado de archivos',
        'healthy' => 'En buen estado',
        'unhealthy' => 'Con problemas',
        'all_checks_passed' => 'Todas las comprobaciones configuradas de los respaldos pasaron.',
        'checks_failed' => 'Una o más comprobaciones configuradas de los respaldos fallaron.',
        'not_configured' => 'Sin configurar',
    ],

    'retention' => [
        'heading' => 'Política de retención',
        'description' => 'Calendario de limpieza automática de los archivos de respaldo.',
        'all_backups' => 'Todos los respaldos',
        'daily_snapshots' => 'Copias diarias',
        'weekly_snapshots' => 'Copias semanales',
        'monthly_snapshots' => 'Copias mensuales',
        'yearly_snapshots' => 'Copias anuales',
        'days' => '{1} :count día|[0,*] :count días',
        'weeks' => '{1} :count semana|[0,*] :count semanas',
        'months' => '{1} :count mes|[0,*] :count meses',
        'years' => '{1} :count año|[0,*] :count años',
    ],

    'widget' => [
        'heading' => 'Resumen de respaldos',
        'listing_unavailable' => 'La lista de respaldos no está disponible.',
        'destination_healthy' => 'Destino en buen estado.',
        'destination_needs_attention' => 'El destino necesita atención.',
        'no_backups_found' => 'No hay respaldos.',
        'available' => [
            'label' => 'Respaldos disponibles',
            'description' => 'Archivos presentes en el almacenamiento',
        ],
        'missing' => [
            'label' => 'Respaldos faltantes',
            'description' => 'Archivos que no se encontraron en el almacenamiento',
            'none' => 'No faltan archivos',
        ],
        'latest' => [
            'label' => 'Último respaldo disponible',
            'none' => 'No hay respaldos disponibles',
        ],
        'total_size' => [
            'label' => 'Tamaño total de los respaldos',
            'description' => 'Tamaño combinado de los archivos listados',
            'sizes_unavailable' => 'No se conoce el tamaño de uno o más archivos',
            'none' => 'No hay respaldos guardados',
        ],
    ],
];
