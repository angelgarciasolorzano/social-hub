<?php

declare(strict_types=1);

/**
 * Texts shared by several settings pages of the React application: the sidebar, the buttons every
 * dialog repeats and the two-factor code fields. A text used by one page only lives in that page's
 * file (`setting/account-settings`, `setting/two-factor`, `setting/trusted-devices`).
 */
return [
    'sidebar' => [
        'group_label' => 'Configuración',
        'account' => 'Cuenta',
        'profile' => 'Perfil',
        'preferences' => 'Preferencias',
        'appearance' => 'Apariencia',
        'security' => 'Seguridad',
        'two_factor' => 'Autenticación en dos pasos',
        'trusted_devices' => 'Dispositivos confiables',
    ],

    'actions' => [
        'cancel' => 'Cancelar',
        'close' => 'Cerrar',
        'retry' => 'Reintentar',
        'retrying' => 'Reintentando...',
    ],

    'two_factor_code' => [
        'otp_label' => 'Código de verificación',
        'recovery_label' => 'Código de recuperación',
        'use_recovery_code' => 'Usar un código de recuperación',
        'use_authenticator_code' => 'Usar un código de mi aplicación autenticadora',
        'recovery_single_use' => 'Cada código de recuperación solo se puede usar una vez.',
    ],
];
