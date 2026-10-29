<?php

declare(strict_types=1);

/**
 * Texts of the admin dashboard widgets.
 */
return [
    'quick_links' => [
        'heading' => 'Acceso rápido',
        'description' => 'Abre una sección a la que tienes acceso.',
        'aria_label' => 'Accesos rápidos del panel',
        'links' => [
            'users' => [
                'label' => 'Usuarios',
                'description' => 'Revisa las cuentas y su estado',
            ],
            'posts' => [
                'label' => 'Publicaciones',
                'description' => 'Revisa publicaciones y comentarios',
            ],
            'activity_log' => [
                'label' => 'Registro de actividad',
                'description' => 'Revisa eventos de seguridad y de administración',
            ],
            'backups' => [
                'label' => 'Respaldos',
                'description' => 'Comprueba la disponibilidad de los respaldos',
            ],
            'announcements' => [
                'label' => 'Anuncios',
                'description' => 'Publica avisos para los usuarios de la aplicación',
            ],
        ],
    ],

    'operational_summary' => [
        'heading' => 'Resumen operativo',
        'account_status' => [
            'label' => 'Estado de las cuentas',
            'active' => '{1} :count activa|[0,*] :count activas',
            'suspended' => '{1} :count suspendida|[0,*] :count suspendidas',
            'recent_changes' => '{1} :count cambio de estado en los últimos 7 días|[0,*] :count cambios de estado en los últimos 7 días',
            'unavailable' => 'No se pudieron cargar las métricas de cuentas',
        ],
        'activity' => [
            'label' => 'Actividad (7 días)',
            'description' => 'Eventos registrados en el registro de actividad',
            'unavailable' => 'No se pudieron cargar las métricas de actividad',
        ],
        'backups' => [
            'label' => 'Último respaldo',
            'none_found' => 'No hay respaldos · 0/0 disponibles',
            'available' => '{1} :available/:total disponible|[0,*] :available/:total disponibles',
            'destination_healthy' => 'Destino en buen estado',
            'destination_needs_attention' => 'El destino necesita atención',
            'unavailable' => 'No se pudo cargar el resumen de respaldos',
        ],
    ],

    'registration_summary' => [
        'heading' => 'Resumen de cuentas',
        'total' => [
            'label' => 'Cuentas totales',
            'description' => 'Todas las cuentas de usuario registradas',
        ],
        'verified' => [
            'label' => 'Cuentas verificadas',
            'description' => 'Cuentas con el correo electrónico verificado',
        ],
        'pending' => [
            'label' => 'Verificación pendiente',
            'description' => 'Cuentas que aún no verifican su correo electrónico',
        ],
    ],

    'registrations_chart' => [
        'heading' => 'Nuevos registros de usuarios',
        'description' => 'Sigue los registros de cuentas a lo largo del tiempo.',
        'empty_heading' => 'Aún no hay registros',
        'empty_description' => 'No hay registros de cuentas en este periodo.',
        'dataset' => 'Registros',
        'filters' => [
            '7_days' => 'Últimos 7 días',
            '30_days' => 'Últimos 30 días',
            '90_days' => 'Últimos 90 días',
            '12_months' => 'Últimos 12 meses',
        ],
        'date_formats' => [
            'day' => 'j M',
            'month' => 'M Y',
        ],
    ],
];
