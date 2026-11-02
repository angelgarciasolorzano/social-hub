<?php

declare(strict_types=1);

/**
 * Texts of the trusted device dialogs (`trustedDevice/components/dialog/`) and of the activity dialog's
 * pieces (`components/table/trustedDeviceActivityDialog/`). Labels the page already shows (statuses,
 * columns, filters, pagination) are reused from `setting/trusted-devices`, and Cancel and Close from
 * `setting/common`. A text split in `_before`/`_after` wraps an emphasized part rendered by the dialog.
 */
return [
    'this_device' => 'este dispositivo',

    'password_confirmation' => [
        'prompt' => 'Para continuar, confirma y escribe tu contraseña',
        'placeholder' => 'Ingresa tu contraseña',
    ],

    'add' => [
        'title' => 'Agregar dispositivo de confianza',
        'description' => 'Este navegador aún no está registrado como dispositivo de confianza. Al agregarlo, no se te solicitará el código de verificación desde este navegador hasta su fecha de expiración. Es una comodidad temporal para omitir ese código, no un segundo factor de autenticación independiente.',
        'personal_only' => 'Agrégalo solo si es tu equipo personal. Puedes revocarlo cuando quieras desde esta pantalla.',
        'submit' => 'Agregar dispositivo',
        'submitting' => 'Agregando...',
        'go_to_two_factor' => 'Ir a autenticación de dos factores',
        'detected_device' => 'Dispositivo detectado',
        'unregistered' => 'Sin registrar',
        'device_description' => ':browser en :os',
        'name_label' => 'Nombre del dispositivo (opcional)',
        'name_placeholder' => 'Mi dispositivo',
        'two_factor_pending_title' => 'Falta confirmar la autenticación de dos factores',
        'two_factor_pending_description' => 'Ya iniciaste la configuración, pero aún no ingresaste el código de tu aplicación autenticadora. Confírmala para poder agregar dispositivos de confianza.',
        'two_factor_disabled_title' => 'Primero activa la autenticación de dos factores',
        'two_factor_disabled_description' => 'Para agregar un dispositivo de confianza necesitas tener la autenticación de dos factores activada y confirmada. Actívala desde la configuración de seguridad.',
    ],

    'already_registered' => [
        'title' => 'Dispositivo ya registrado',
        'description' => 'Este dispositivo ya está registrado como de confianza en tu cuenta. No es necesario agregarlo nuevamente.',
        'changes_title' => '¿Necesitas hacer cambios?',
        'changes_description' => 'Puedes administrar este dispositivo desde la lista de dispositivos de confianza.',
        'go_to_devices' => 'Ir a dispositivos de confianza',
    ],

    'details' => [
        'title' => 'Detalles del dispositivo',
        'description_revoked' => 'Consulta la información del dispositivo que fue revocado de tu cuenta.',
        'description_expired' => 'Consulta la información de un dispositivo cuya confianza ya expiró.',
        'description_active' => 'Consulta la información completa de este dispositivo de confianza.',
        'information' => 'Información del dispositivo',
        'state_revoked' => 'Ya no es de confianza, aunque su expiración siga vigente. En tu próximo inicio de sesión se pedirá el código de verificación.',
        'state_expired' => 'El periodo de confianza expiró. En tu próximo inicio de sesión desde este dispositivo se pedirá el código de verificación.',
        'state_active' => 'Este dispositivo sigue siendo de confianza. No se te pedirá un código de verificación al iniciar sesión desde aquí hasta su fecha de expiración.',
        'registered' => 'Registrado',
    ],

    'expired' => [
        'title' => 'Confianza expirada',
        'description' => 'Este dispositivo ya fue registrado pero su confianza expiró. Para volver a confiar en él, renueva su confianza desde la lista de dispositivos expirados.',
        'expired_on' => 'Expiró el',
        'go_to_expired' => 'Ir a dispositivos expirados',
        'trust_again_title' => '¿Quieres volver a confiar en este dispositivo?',
        'trust_again_description' => 'Renueva la confianza para extender la fecha de expiración sin volver a registrarlo.',
    ],

    'force_destroy' => [
        'title' => 'Eliminar permanentemente',
        'description' => 'Eliminarás permanentemente :device de tu cuenta. Esta acción es definitiva y no podrás volver a confiar en él.',
        'submit' => 'Eliminar definitivamente',
        'submitting' => 'Eliminando...',
        'consequences_title' => 'Consecuencias de la eliminación permanente',
        'consequences' => [
            'deleted' => 'Este dispositivo se eliminará permanentemente de la base de datos.',
            'no_reactivation' => 'Ya no podrás reactivarlo desde la sección "Revocados".',
            'history' => 'Tu historial de actividad seguirá mostrando las acciones realizadas con este dispositivo, pero ya no permitirá abrirlas para ver los detalles.',
        ],
        'terms' => 'Entiendo que esta acción es irreversible.',
    ],

    'reactivation' => [
        'title' => 'Reactivar dispositivo',
        'description' => 'Para volver a confiar en este dispositivo, ingresa un código de verificación de tu aplicación autenticadora o uno de tus códigos de recuperación. Recordar un navegador es una comodidad temporal, no un segundo factor de autenticación independiente.',
        'submit' => 'Reactivar',
        'submitting' => 'Reactivando...',
        'why_code_title' => '¿Por qué pedimos código?',
        'why_code_before' => 'Por seguridad, reactivar',
        'why_code_after' => 'requiere autenticarte de nuevo. Ingresa el código de 6 dígitos de tu aplicación autenticadora o uno de tus códigos de recuperación.',
    ],

    'rename' => [
        'title' => 'Renombrar dispositivo',
        'description' => 'Asigna un nombre personalizado para identificar este dispositivo fácilmente.',
        'name_label' => 'Nombre del dispositivo',
        'name_placeholder' => 'Renombrar dispositivo',
        'name_help' => 'Este será el nombre con el que identificarás este dispositivo.',
        'preview_title' => 'Vista previa',
        'preview_description' => 'Este dispositivo se mostrará como:',
        'submit' => 'Guardar cambios',
        'submitting' => 'Guardando cambios...',
    ],

    'renew' => [
        'title' => 'Renovar confianza',
        'description' => 'Extiende el periodo de confianza para evitar que se te solicite el código de verificación en este dispositivo. Es una comodidad temporal, no un segundo factor de autenticación independiente. Por seguridad, confirma con un código de tu aplicación autenticadora.',
        'submit' => 'Renovar confianza',
        'submitting' => 'Renovando...',
        'renewal_period' => '1 mes',
        'current_expiration' => 'Expiración actual',
        'new_expiration' => 'Nueva expiración',
        'notice' => 'No se te pedirá el código de verificación en este dispositivo hasta la nueva fecha de expiración.',
    ],

    'revoke' => [
        'title' => 'Revocar dispositivo',
        'description' => 'Revocarás :device. Dejará de ser de confianza y se te volverá a solicitar el código de verificación la próxima vez que inicies sesión desde él.',
        'submit' => 'Revocar dispositivo',
        'submitting' => 'Revocando...',
        'consequences_title' => 'Consecuencias de la revocación',
        'consequences' => [
            'not_trusted' => 'Este dispositivo ya no estará registrado como de confianza.',
            'code_again' => 'Se te pedirá el código de verificación la próxima vez que inicies sesión desde este dispositivo.',
        ],
        'terms' => 'Entiendo las consecuencias de revocar este dispositivo.',
    ],

    'revoke_all' => [
        'title' => 'Revocar todos los dispositivos',
        'description' => 'Revocarás todos los dispositivos de confianza vinculados a tu cuenta. La próxima vez que inicies sesión desde cualquiera de ellos, se te volverá a solicitar el código de verificación.',
        'single_hint_before' => 'Si solo deseas eliminar uno, puedes revocarlo',
        'single_hint_emphasis' => 'individualmente',
        'single_hint_after' => 'desde la lista de dispositivos.',
        'submit' => 'Revocar todos',
        'submitting' => 'Revocando...',
        'device_expires' => 'Expira el :date',
        'consequences_title' => 'Consecuencias de revocar todos los dispositivos',
        'consequences' => [
            'all_removed' => 'Se eliminarán todos los dispositivos de confianza vinculados a tu cuenta.',
            'code_again' => 'Se te volverá a solicitar el código de verificación (2FA) en esos dispositivos.',
            'irreversible' => 'Esta acción no se puede deshacer.',
        ],
        'devices_count' => '{1} :count dispositivo será revocado|[0,*] :count dispositivos serán revocados',
        'no_devices_title' => 'No hay dispositivos para revocar',
        'includes_all' => 'Incluye todos los dispositivos de confianza registrados actualmente.',
        'no_devices_description' => 'Cuando registres un nuevo dispositivo de confianza, aparecerá aquí para que puedas revocarlo junto con los demás.',
        'empty' => 'Aún no tienes dispositivos de confianza registrados.',
        'terms' => 'Entiendo que esta acción eliminará todos mis dispositivos de confianza.',
    ],

    'revoked' => [
        'title' => 'Dispositivo revocado',
        'description' => 'Este dispositivo ya fue registrado pero lo revocaste. Para volver a confiar en él, reactívalo desde la lista de dispositivos revocados.',
        'go_to_revoked' => 'Ir a dispositivos revocados',
        'trust_again_title' => '¿Quieres volver a confiar en este dispositivo?',
        'trust_again_description' => 'Reactivarlo desde la lista de revocados restaura la fila con un token nuevo por seguridad.',
    ],

    'activity' => [
        'title' => 'Actividad reciente de dispositivos',
        'description' => 'Historial de eventos relacionados con tus dispositivos de confianza.',
        'search_label' => 'Buscar actividad de dispositivos',
        'search_placeholder' => 'Buscar por dispositivo, nombre o IP...',
        'per_page' => 'Eventos por página',
        'no_events' => 'Sin eventos',
        'showing' => '{1} Mostrando :from-:to de :total evento|[0,*] Mostrando :from-:to de :total eventos',
        'empty_filtered_title' => 'Sin actividad para los filtros seleccionados.',
        'empty_filtered_description' => 'Prueba cambiar el rango temporal, el tipo de acción o el término de búsqueda.',
        'empty_title' => 'Aún no hay actividad registrada.',
        'empty_description' => 'Los eventos de tus dispositivos de confianza aparecerán aquí.',
        'ip' => 'IP: :ip',
        'unknown_os' => 'Sistema operativo desconocido',
        'action_filter' => 'Acción',
        'range_filter' => 'Filtrar por rango temporal',
        'active_filters' => 'Filtros activos de actividad',
        'chip_action' => 'Acción: :value',
        'chip_range' => 'Rango: :value',
        'pagination_label' => 'Paginación de la actividad de dispositivos',
        'current_page' => 'Página actual :page',
        'go_to_page' => 'Ir a la página :page',
        'actions' => [
            'created' => 'Agregados',
            'renewed' => 'Renovados',
            'renamed' => 'Renombrados',
            'revoked' => 'Revocados',
            'revoked_all' => 'Todos revocados',
            'reactivated' => 'Reactivados',
        ],
        'since_days' => [
            'last_7_days' => 'Últimos 7 días',
            'last_30_days' => 'Últimos 30 días',
            'last_90_days' => 'Últimos 90 días',
            'last_180_days' => 'Últimos 180 días',
            'all' => 'Todos',
        ],
    ],

    'keyboard_shortcuts' => [
        'description' => 'Los atajos globales empiezan con G. Para las acciones por dispositivo, selecciona una fila haciendo clic en ella o enfocando su botón de acciones (⋯); luego usa los atajos que empiezan con F.',
        'groups' => [
            'general' => 'GENERAL',
            'navigation' => 'NAVEGACIÓN',
            'actions' => 'ACCIONES',
        ],
        'open_help' => 'Abrir esta ayuda',
        'open_help_keys' => 'Mayús y signo de interrogación',
        'then' => ', luego ',
        'for_selected_row' => ':action para la fila seleccionada',
    ],

    'recommendations' => [
        'title' => 'Recomendaciones de seguridad',
        'description' => 'Sigue estas recomendaciones para mantener tu cuenta y dispositivos de confianza seguros.',
        'footer' => 'Los dispositivos de confianza agilizan tu inicio de sesión, pero conviene revisarlos de vez en cuando.',
        'preview_title' => 'Así se verá en tu lista',
        'example' => 'Ejemplo',
    ],

    'summary' => [
        'description' => 'Aquí puedes ver el detalle de la distribución y estado de tus dispositivos de confianza.',
        'percentage_of_total' => ':percentage% del total',
        'desktop' => 'Escritorio / Laptop',
        'mobile' => 'Móvil / Tablet',
        'by_type_title' => 'Dispositivo por tipo',
        'by_type_description' => 'Cantidad de dispositivos por categoría',
        'no_active' => 'Aún no tienes dispositivos activos.',
        'by_status_title' => 'Distribución por estado',
        'by_status_description' => 'Porcentaje del total de dispositivos',
    ],
];
