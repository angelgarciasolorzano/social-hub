<?php

declare(strict_types=1);

/**
 * Texts of the two-factor authentication settings page (`setting/modules/twoFactor`), its setup wizard
 * and dialogs, and the flashes and validation messages of its controller and requests. Shared buttons
 * such as Cancel and the code fields live in `setting/common`.
 */
return [
    'page_title' => 'Autenticación de dos factores',

    'labels' => [
        'backup_codes' => 'Códigos de respaldo',
        'trusted_devices' => 'Dispositivos de confianza',
        'activation_date' => 'Fecha de activación',
        'method_totp' => 'TOTP (Aplicación)',
        'understood' => 'Entendido',
        'back' => 'Volver',
        'continue' => 'Continuar',
        'confirm' => 'Confirmar',
        'download_txt' => 'Descargar .txt',
        'password' => 'Contraseña',
        'regenerate_codes' => 'Regenerar códigos',
        'disable' => 'Desactivar 2FA',
        'loading_backup_codes' => 'Cargando códigos de respaldo.',
        'no_backup_codes' => 'No hay códigos de respaldo disponibles.',
    ],

    'disabled_view' => [
        'title' => 'Autenticación de dos factores (2FA)',
        'description' => 'Añade una capa adicional de seguridad a tu cuenta. Con 2FA, además de tu contraseña, se te solicitará un código único para verificar tu identidad.',
        'status' => 'Desactivado',
        'continue_setup' => 'Continuar configuración',
        'activate' => 'Activar 2FA',
        'activating' => 'Activando...',
        'activate_failed' => 'No se pudo activar 2FA. Inténtalo de nuevo.',
        'pin_title' => 'Este PIN se solicitará cada vez que inicies sesión.',
        'pin_description' => 'Puedes obtenerlo desde una aplicación compatible con TOTP en tu teléfono.',
        'questions' => '¿Tienes dudas?',
        'faq_link' => 'Consulta nuestros FAQ',
        'security_priority' => 'Tu seguridad es nuestra prioridad.',
        'benefits_title' => 'Beneficios de activar 2FA',
        'important_details_title' => 'Detalles importantes',
        'how_it_works' => 'Cómo funciona',
        'requirements_title' => 'Requisitos',
        'recommended_apps' => 'Aplicaciones recomendadas',
    ],

    'benefits' => [
        'security' => [
            'title' => 'Más seguridad',
            'description' => 'Protege tu cuenta contra accesos no autorizados.',
        ],
        'verification' => [
            'title' => 'Verificación adicional',
            'description' => 'Requiere un código único además de tu contraseña.',
        ],
        'standard' => [
            'title' => 'Estándar confiable',
            'description' => 'Utilizamos TOTP, un estándar ampliamente reconocido y seguro.',
        ],
    ],

    'important_details' => [
        'standard' => [
            'title' => 'Estándar confiable',
            'description' => 'Guarda los códigos de respaldo que te proporcionaremos. Te permitirán acceder a tu cuenta si pierdes el teléfono.',
        ],
        'backup_codes' => [
            'title' => 'Códigos de respaldo',
            'description' => 'Cada código de recuperación se puede usar una vez para acceder a tu cuenta y se eliminará después de su uso. Si necesitas más, genera nuevos códigos después de activar 2FA.',
        ],
        'trusted_devices' => [
            'title' => 'Dispositivos de confianza',
            'description' => 'Marca tus dispositivos como confiables para no tener que ingresar un código de verificación cada vez que inicies sesión desde ellos.',
        ],
        'support' => [
            'title' => '¿Problemas?',
            'description' => 'Si no puedes acceder a tu cuenta, usa un código de respaldo o contacta al soporte para obtener ayuda.',
        ],
    ],

    'operation_steps' => [
        'scan' => [
            'title' => 'Escanea el código QR',
            'description' => 'Abre tu aplicación autenticadora (Google Authenticator, Authy, 1Password, etc.) y escanea el código.',
        ],
        'get_code' => [
            'title' => 'Obtén tu código',
            'description' => 'La aplicación generará códigos de 6 dígitos que cambian cada 30 segundos.',
        ],
        'enter_code' => [
            'title' => 'Ingresa el código',
            'description' => 'Ingresa el código de 6 dígitos para verificar y activar 2FA en tu cuenta.',
        ],
    ],

    'requirements' => [
        'totp_app' => 'Una aplicación autenticadora compatible con TOTP',
        'mobile_access' => 'Acceso a tu teléfono móvil',
        'internet_connection' => 'Conexión a internet (para la configuración inicial)',
    ],

    'enabled_view' => [
        'security_options' => 'Opciones de seguridad',
        'active_title' => '2FA está activo',
        'active_description' => 'Tu cuenta está protegida con autenticación de dos factores.',
        'method' => 'Método:',
        'manage' => 'Administrar',
        'manage_menu' => 'Gestión de acciones de 2FA',
        'summary_title' => 'Resumen de seguridad',
        'safety_tips_title' => 'Consejos de seguridad',
        'view_details' => 'Ver detalles',
    ],

    'summary' => [
        'status_title' => 'Estado de 2FA',
        'status_description' => 'La autenticación de dos factores está activa en tu cuenta.',
        'status_active' => 'Activado',
        'trusted_devices_count' => '{1} :count dispositivo de confianza configurado.|[0,*] :count dispositivos de confianza configurados.',
        'configure' => 'Configurar',
        'view_devices' => 'Ver dispositivos',
        'backup_codes_available' => 'Tienes :available de :total códigos disponibles.',
        'not_activated' => 'Aún no has activado la autenticación de dos factores.',
        'activated_on' => 'Activaste 2FA el :date',
    ],

    'security_options' => [
        'backup_codes' => [
            'title' => 'Códigos de respaldo',
            'description' => 'Usa estos códigos si no tienes acceso a tu autenticador.',
        ],
        'regenerate_codes' => [
            'title' => 'Regenerar códigos',
            'description' => 'Genera nuevos códigos de respaldo. Los actuales dejarán de funcionar.',
        ],
        'disable' => [
            'title' => 'Desactivar 2FA',
            'description' => 'Desactiva la autenticación de dos factores en tu cuenta.',
        ],
    ],

    'safety_tips' => [
        'save_backup_codes' => [
            'title' => 'Guarda tus códigos',
            'description' => 'Almacena tus códigos de respaldo en un lugar seguro y accesible para ti.',
        ],
        'use_trusted_devices' => [
            'title' => 'Usa dispositivos de confianza',
            'description' => 'Marca dispositivos como confiables para evitar verificaciones frecuentes.',
        ],
        'keep_app_updated' => [
            'title' => 'Mantén tu aplicación actualizada',
            'description' => 'Asegúrate de tener la última versión de tu aplicación autenticadora para garantizar la mejor seguridad.',
        ],
    ],

    'manage_actions' => [
        'view_codes' => 'Ver códigos de respaldo',
        'regenerate_codes' => 'Regenerar códigos',
        'trusted_devices' => 'Dispositivos de confianza',
        'disable' => 'Desactivar 2FA',
    ],

    'setup_steps' => [
        'qr' => [
            'title' => 'Habilitar autenticación de dos factores',
            'description' => 'Escanea el código QR con tu aplicación autenticadora.',
        ],
        'manual' => [
            'title' => 'No puedes escanear el código QR',
            'description' => 'Ingresa esta clave manualmente en tu aplicación autenticadora.',
        ],
        'verify' => [
            'title' => 'Verificar código de autenticación',
            'description' => 'Ingresa el código de 6 dígitos que muestra tu aplicación para confirmar que funciona correctamente.',
        ],
        'success' => [
            'title' => '¡2FA activado correctamente!',
            'description' => 'Guarda estos códigos de respaldo en un lugar seguro.',
        ],
    ],

    'choose_method' => [
        'qr_alt' => 'Código QR para configurar la autenticación de dos factores',
        'qr_ready' => 'Código QR listo para escanear.',
        'qr_loading' => 'Cargando código QR.',
        'cannot_scan' => '¿No puedes escanear el código?',
        'tip_title' => 'Consejo',
        'tip_description' => 'Abre tu aplicación y usa la opción para agregar una nueva cuenta.',
    ],

    'manual_setup' => [
        'title' => 'Código manual',
        'instructions' => 'En tu aplicación, selecciona "Ingresar clave manualmente" o "Agregar cuenta manualmente" y pega esta clave.',
        'copy' => 'Copiar código manual',
        'loading' => 'Cargando código manual.',
        'copied_title' => 'Clave copiada al portapapeles',
        'copied_countdown' => '{1} Este mensaje desaparecerá automáticamente en :count segundo.|[0,*] Este mensaje desaparecerá automáticamente en :count segundos.',
        'time_left' => 'Tiempo restante',
    ],

    'verify_otp' => [
        'hint' => 'El código cambia cada 30 segundos en tu aplicación.',
    ],

    'success_step' => [
        'load_failed_title' => 'No pudimos mostrar tus códigos de respaldo.',
        'load_failed_description' => 'Inténtalo de nuevo en unos segundos para volver a cargarlos.',
        'ready' => 'Códigos de respaldo listos.',
        'important' => 'Importante',
        'single_use' => 'Cada código solo se puede usar una vez. Guarda o descarga estos códigos ahora.',
    ],

    'error_alert' => [
        'title' => 'Algo salió mal',
        'description' => 'No pudimos cargar la información. Inténtalo de nuevo.',
    ],

    'fetch_errors' => [
        'qr_code' => 'No se pudo cargar el código QR.',
        'setup_key' => 'No se pudo cargar la clave de configuración.',
        'recovery_codes' => 'No se pudieron cargar los códigos de recuperación.',
    ],

    'disable_dialog' => [
        'title' => 'Desactivar la autenticación de dos factores',
        'description' => 'Por seguridad, necesitamos verificar tu identidad antes de desactivar la autenticación de dos factores. Ingresa el código de tu aplicación autenticadora o uno de tus códigos de recuperación.',
        'warning_title' => '¿Estás seguro de que quieres desactivar la autenticación de dos factores?',
        'warning_description' => 'Desactivar 2FA hará tu cuenta más vulnerable. Solo deberías hacerlo si tienes un motivo específico.',
        'submitting' => 'Desactivando 2FA...',
    ],

    'regenerate_dialog' => [
        'title' => 'Regenerar códigos de respaldo',
        'description' => '¿Estás seguro de que quieres regenerar tus códigos de respaldo? Esta acción no se puede deshacer.',
        'warning_title' => 'Los códigos actuales dejarán de funcionar.',
        'warning_description' => 'Una vez que generes nuevos códigos, los anteriores no podrán usarse.',
        'password_prompt' => 'Para continuar, escribe tu contraseña',
        'submitting' => 'Regenerando...',
    ],

    'activation_details' => [
        'description' => 'Aquí puedes ver cuándo activaste la autenticación de dos factores (2FA) en tu cuenta.',
        'time' => 'Hora',
        'method' => 'Método',
        'protect_title' => 'Protege tu cuenta',
        'protect_description' => 'Si no reconoces esta actividad, te recomendamos cambiar tu contraseña y revisar tus dispositivos de confianza.',
        'go_to_devices' => 'Ir a dispositivos de confianza',
        'go_to_devices_hint' => 'Te llevará a la sección de dispositivos de confianza.',
    ],

    'devices_panel' => [
        'description' => 'Los dispositivos de confianza reducen la frecuencia con la que se te solicita el código de verificación.',
        'revoke_hint' => 'Revoca cualquier dispositivo que ya no utilices para mantener tu cuenta segura.',
        'registered' => 'Dispositivos registrados',
        'add' => 'Agregar dispositivo',
        'empty' => 'No tienes dispositivos de confianza activos.',
        'show_all' => 'Mostrar todos los dispositivos',
        'revoke_all' => 'Revocar todos',
        'tips_title' => 'Consejos',
        'tips' => [
            'only_yours' => 'Usa dispositivos que sean solo tuyos.',
            'sign_out' => 'Cierra sesión en dispositivos que ya no uses.',
            'delay' => 'Los cambios pueden tardar unos minutos en reflejarse.',
        ],
        'load_failed_title' => 'No se pudieron cargar los dispositivos de confianza',
        'load_failed_description' => 'Comprueba tu conexión e inténtalo de nuevo.',
        'loading' => 'Cargando dispositivos de confianza.',
        'last_used' => 'Último uso: :time',
    ],

    'recovery_codes' => [
        'load_failed' => 'No se pudieron cargar los códigos de respaldo.',
        'save_title' => 'Guarda estos códigos en un lugar seguro.',
        'save_description' => 'Te permitirán acceder a tu cuenta si pierdes el acceso a tu aplicación autenticadora.',
        'retry_hint' => 'Intenta cargar los códigos de respaldo otra vez.',
        'load' => 'Cargar códigos',
        'available' => 'Códigos disponibles',
        'available_count' => ':available de :total',
        'copied' => 'Copiado',
        'copy_all' => 'Copiar códigos',
        'last_regenerated' => 'Última regeneración',
        'code_copied' => 'Código de respaldo :code copiado',
        'copy_code' => 'Copiar código de respaldo :code',
    ],

    'download' => [
        'title' => 'Códigos de respaldo para autenticación de dos factores',
        'account' => 'Cuenta: :email',
        'generated' => 'Generados: :date',
        'notice' => 'Guárdalos en un lugar seguro. Cada código solo puede usarse una vez.',
    ],

    'messages' => [
        'recovery_codes_regenerated' => 'Códigos de recuperación regenerados exitosamente.',
        'disabled' => 'La autenticación de dos factores ha sido desactivada correctamente.',
    ],

    'validation' => [
        'password_required' => 'Debes confirmar tu contraseña.',
        'password_mismatch' => 'La contraseña proporcionada no coincide con tu contraseña actual.',
        'password_incorrect' => 'La contraseña proporcionada no es correcta.',
        'code_required' => 'Debes ingresar el código de autenticación.',
        'code_too_long' => 'El código es demasiado largo.',
        'code_format' => 'Ingresa un código de 6 dígitos o un código de recuperación válido.',
        'code_invalid' => 'El código de autenticación proporcionado no es válido.',
    ],
];
