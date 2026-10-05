# Plan: SOC-34: Dashboard unificado de configuración de cuenta

## Overview

Issue: [SOC-34](https://linear.app/social-hub-ang/issue/SOC-34/feature-recreate-the-profile-settings-dashboard-from-the-supplied) — In Review; prioridad inicial Medium.

Modo confirmado: **EXPANSION**. Revisión predeterminada: **codex**. La ruta crítica avanza desde la infraestructura User y AccountSettings hasta los diálogos de acciones, elimina la vista independiente de contraseña, sincroniza documentación y QA, y termina registrando que el estado real se difiere a SOC-35.

El estado real de la cuenta queda fuera del alcance actual. El usuario decidió diferirlo a SOC-35 hasta que existan un panel administrativo y roles/permisos; SOC-34 no debe mostrar un estado ficticio ni esperar a esa implementación futura.

## Scope Challenge

- Se reutilizan el shell actual de Settings, el perfil público, el cambio de contraseña y la eliminación existente; la pantalla actual solo edita nombre/correo y no cubre los nuevos datos.
- La implementación nueva se organiza como AccountSettings bajo User y como accountSettings bajo el módulo frontend setting.
- La expansión acordada incluye modernizar la ubicación de UserFactory/UserSeeder y alojar la migración nueva en app/User/Database/Migrations.
- Profile y Preferences existentes permanecen en sus ubicaciones actuales. Los archivos iniciales de Laravel en database/migrations no se mueven.
- El estado real de la cuenta se difiere a SOC-35 y solo se implementará cuando existan el panel administrativo y roles/permisos.

## Prerequisites

- Mantener la página en GET /setting/profile (profile.edit) y el guardado en PATCH /setting/profile (profile.update), ambos bajo auth y verified.
- El módulo User carga las migraciones de app/User/Database/Migrations; los archivos iniciales existentes en database/migrations permanecen allí.
- La cuenta objetivo siempre se obtiene del usuario autenticado. Ningún ID enviado por el navegador puede seleccionar otra cuenta para leer o actualizar.
- UserResource continúa siendo seguro para el perfil público; la página privada usa un recurso AccountSettingsResource.
- La selección de idioma persiste uno de los idiomas existentes en resources/lang (en o es) y se rehidrata al recargar. La traducción completa de toda la aplicación React queda fuera de este plan.
- El último acceso se registra tras un Login exitoso. Intentos fallidos y el paso previo de un challenge 2FA no cuentan como acceso completado.
- Cada paso de implementación termina con resumen y pausa para revisión; no se continúa hasta que el usuario lo pida.

## Non-Goals

- Mover los módulos existentes User/Profile o User/Preferences a Modules/.
- Mover las migraciones iniciales de Laravel ni las migraciones existentes bajo database/migrations.
- Implementar Ver actividad como navegación, diálogo o solicitud.
- Persistir o activar Preferencias de comunicación; sus indicadores son visuales y estáticos.
- Cambiar la validación o la lógica backend de PasswordController.update; el nuevo diálogo reutiliza el endpoint actual y conserva la revocación de dispositivos confiables.
- Habilitar edición de correo o implementar un flujo de cambio/verificación de correo.
- Rediseñar otras páginas del producto o traducir toda la interfaz React.
- Mostrar o gestionar el estado real de cuenta en SOC-34; queda diferido a SOC-35 hasta que existan panel administrativo y roles/permisos.

## Contracts

### Rutas existentes

- Conservar GET /setting/profile como profile.edit y PATCH /setting/profile como profile.update.
- Mantener auth y verified desde app/User/routes/routes.php.
- Conservar /profile como perfil público propio, el endpoint PUT setting.password.update y user.destroy. El GET setting.password.edit se elimina; el cambio de contraseña se inicia desde un diálogo en la página de ajustes de cuenta.

### Datos privados y públicos

- AccountSettingsResource devuelve datos de la cuenta autenticada: id, name, email, phone, preferredLocale, biography, createdAt y lastLoginAt.
- El resumen muestra name como Usuario, ya que User no tiene un campo username.
- No añadir teléfono, biografía, idioma ni lastLoginAt al UserResource compartido por el perfil público.

### Validación y seguridad

- El formulario acepta name, phone opcional, preferredLocale dentro de en/es y biography opcional de máximo 160 caracteres.
- El correo se presenta de solo lectura y no forma parte del request validado de AccountSettings.
- Las operaciones de lectura, guardado y borrado actúan solo sobre Auth::user(); contraseña incorrecta conserva la cuenta.

### Último acceso

- Usar el evento de autenticación exitosa que ocurre en los caminos Auth::login actuales, incluido el acceso por dispositivo confiable.
- El challenge 2FA pendiente y los errores de credenciales no actualizan last_login_at.

### Elementos estáticos

- Ver actividad no tiene href, handler, modal ni solicitud.
- Los indicadores de comunicación no son controles interactivos ni guardan preferencias.

### Estado de cuenta

- No se inventa un estado ni se deduce de email_verified_at, del último acceso o de la sesión activa.
- SOC-34 no implementa ni muestra un estado real. SOC-35 cubre los estados activa/suspendida y solo debe iniciarse cuando existan panel administrativo y roles/permisos.

### Cambio y eliminación de contraseña/cuenta

- El diálogo de cambio de contraseña reutiliza PasswordController.update y conserva la validación actual y la revocación de dispositivos confiables; las sesiones actuales permanecen activas.
- UserController.destroy valida la contraseña actual, cierra sesión, elimina el registro User e invalida la sesión. El texto no afirma efectos sobre contenido relacionado sin verificar sus relaciones/cascadas reales.

## Existing Code Leverage

- resources/js/modules/setting/modules/profile/EditProfile.tsx es la página a reemplazar y SettingLayout ya envuelve páginas del área.
- app/User/Profile/Controllers/ProfileController.php conserva el perfil público; su método edit actual puede salir al nuevo controlador de AccountSettings.
- app/User/Controllers/UserController.php ya actualiza usuarios desde Auth::user() y borra con current_password; el borrado se reutiliza con route user.destroy.
- PasswordController conserva la acción update; AccountSettingsPasswordDialog la usa y mantiene el aviso de revocación de dispositivos confiables. La vista EditPassword ya se retiró.
- resources/js/shared/components/form/PasswordInput.tsx está exportado desde el barrel form y admite ref, autocomplete y errores, con control para mostrar/ocultar la contraseña.
- AccountSettingsDeleteDialog.tsx ya usa useDialog; su campo actual es un Input type=password y el aviso solo indica permanencia, eliminación de datos y cierre de sesión.
- app/User/Resources/UserResource.php se mantiene como serialización pública; la configuración privada necesita AccountSettingsResource.
- app/User/Modules/TwoFactor y app/Auth/Modules/TrustedDevice ilustran módulos de feature con Tests co-localizados.
- app/Auth/Providers/AuthServiceProvider.php carga migraciones desde el directorio Database del dominio y sirve como patrón para User.
- tests/Feature/Settings/ProfileUpdateTest.php contiene expectativas obsoletas: componente antiguo y route profile.destroy inexistente; se migrará y actualizará.
- vite.config.ts configura Wayfinder en resources/js/shared/wayfinder con formVariants habilitado.
- package.json no define un runner de pruebas frontend; la revisión visual y de teclado será manual.

## Tasks

### TASK-001: Mover UserFactory a Database/Factories

Alinear la factory del modelo User con el patrón del dominio Auth, conservando sus estados y configuración de MediaLibrary.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** Ninguna  
**Review:** codex

**Acceptance Criteria:**
1. User resuelve UserFactory desde App\User\Database\Factories y User::factory() sigue creando usuarios.
2. El callback afterCreating conserva la lógica de imágenes de perfil/cobertura y su comportamiento por entorno.
3. No quedan imports ni referencias a App\User\Factories\UserFactory.

**writeScope:**
- Mover app/User/Factories/UserFactory.php a app/User/Database/Factories/UserFactory.php.
- Actualizar el atributo UseFactory y el PHPDoc HasFactory de app/User/Models/User.php.
- Actualizar el import de UserFactory en app/Post/Factories/PostFactory.php.

**Moves:**
- app/User/Factories/UserFactory.php -> app/User/Database/Factories/UserFactory.php

**validateCommand:**
```text
rtk php -l app/User/Database/Factories/UserFactory.php
```

### TASK-002: Mover UserSeeder a Database/Seeders

Alinear el seeder de User con el patrón del dominio y actualizar la llamada desde el seeder raíz.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** Ninguna  
**Review:** codex

**Acceptance Criteria:**
1. UserSeeder queda bajo App\User\Database\Seeders y DatabaseSeeder lo importa desde el namespace nuevo.
2. El seeder conserva la creación de usuarios de prueba y su perfil de acceso local.
3. Un namespace incorrecto o clase ausente hace fallar lint antes de integrar.

**writeScope:**
- Mover app/User/Seeders/UserSeeder.php a app/User/Database/Seeders/UserSeeder.php.
- Actualizar el import UserSeeder en database/seeders/DatabaseSeeder.php.
- Actualizar la referencia del seeder en el PHPDoc de tests/Pest.php.

**Moves:**
- app/User/Seeders/UserSeeder.php -> app/User/Database/Seeders/UserSeeder.php

**validateCommand:**
```text
rtk php -l app/User/Database/Seeders/UserSeeder.php && rtk php -l database/seeders/DatabaseSeeder.php
```

### TASK-003: Registrar las migraciones del dominio User

Hacer que UserServiceProvider descubra las migraciones nuevas de app/User/Database/Migrations, siguiendo el patrón de Auth.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** Ninguna  
**Review:** codex

**Acceptance Criteria:**
1. UserServiceProvider carga app/User/Database/Migrations desde su boot.
2. Las migraciones originales en database/migrations no se mueven ni renombran.
3. Artisan puede descubrir migraciones del directorio User sin duplicar las migraciones raíz.

**writeScope:**
- Añadir loadMigrationsFrom para app/User/Database/Migrations en app/User/Providers/UserServiceProvider.php.

**validateCommand:**
```text
rtk php artisan migrate:status --no-interaction
```

### TASK-004: Persistir los nuevos datos de perfil

Añadir una migración modular y actualizar User y su factory para teléfono, idioma preferido, biografía y último acceso. La decisión del estado de cuenta queda fuera de esta migración.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** TASK-001, TASK-003  
**Review:** codex

**Acceptance Criteria:**
1. Una migración nueva bajo app/User/Database/Migrations añade phone nullable, preferred_locale no nulo de 2 caracteres con default en, biography nullable de 160 caracteres y last_login_at nullable.
2. User expone los campos editables de manera explícita y convierte last_login_at a fecha inmutable; last_login_at no queda asignable desde el formulario.
3. La migración down elimina solo las columnas nuevas y no modifica ni reemplaza las migraciones iniciales de Laravel.
4. UserFactory define un valor realista o null explícito para cada columna persistida de User, excepto id y timestamps administrados por Eloquent.

**writeScope:**
- Crear la migración con php artisan make:migration add_account_settings_fields_to_users_table --table=users --path=app/User/Database/Migrations --no-interaction.
- Añadir los atributos y casts de perfil necesarios a app/User/Models/User.php.
- Completar UserFactory con valores realistas o null explícito para todos los campos persistidos de User, incluyendo los campos nullable existentes de 2FA y los nuevos atributos; omitir solo id y timestamps administrados por Eloquent.

**validateCommand:**
```text
rtk php artisan migrate --pretend --path=app/User/Database/Migrations --no-interaction
```

### TASK-005: Registrar el último acceso en logins exitosos

Crear un listener de AccountSettings para el evento Login y registrarlo en UserEventServiceProvider.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-004  
**Review:** codex

**Acceptance Criteria:**
1. Un evento Login exitoso actualiza last_login_at para el User que acaba de autenticarse.
2. El acceso normal y el camino de Auth::login por dispositivo confiable actualizan el campo.
3. Un intento fallido o un login.id pendiente de 2FA no cambia last_login_at.

**writeScope:**
- Crear app/User/Modules/AccountSettings/Listeners/TrackLastLogin.php.
- Registrar el listener de Illuminate\Auth\Events\Login en app/User/Providers/UserEventServiceProvider.php.

**validateCommand:**
```text
rtk php -l app/User/Modules/AccountSettings/Listeners/TrackLastLogin.php && rtk php -l app/User/Providers/UserEventServiceProvider.php
```

### TASK-006: Crear un recurso privado de AccountSettings

Serializar los datos de configuración de la cuenta en un JsonResource de la feature, manteniendo UserResource acotado a datos públicos.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-004, TASK-005  
**Review:** codex

**Acceptance Criteria:**
1. AccountSettingsResource expone datos reales de la cuenta autenticada y los timestamps reales createdAt y lastLoginAt.
2. El recurso incluye teléfono, preferredLocale y biography solo para la página privada.
3. UserResource conserva su contrato público y no serializa los nuevos datos privados.

**writeScope:**
- Crear app/User/Modules/AccountSettings/Resources/AccountSettingsResource.php con PHPDoc de forma tipada.

**validateCommand:**
```text
rtk php -l app/User/Modules/AccountSettings/Resources/AccountSettingsResource.php
```

### TASK-007: Implementar controlador y validación de AccountSettings

Crear el controlador privado para mostrar y actualizar la configuración, junto con una FormRequest que use Fluent Validation y un Data object para transportar los valores validados con tipos explícitos.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-006  
**Review:** codex

**Acceptance Criteria:**
1. El GET de ajustes devuelve AccountSettingsResource para el usuario autenticado y renderiza setting/modules/accountSettings/AccountSettings.
2. AccountSettingsUpdateRequest conserva la autorización y validación con HasFluentRules/FluentRule; AccountSettingsUpdateData centraliza las reglas y expone propiedades tipadas a partir de validated(), sin volver a validar.
3. Los campos opcionales ausentes conservan su valor y null explícito lo limpia; email y last_login_at no son editables, y solo se actualiza el usuario autenticado.

**writeScope:**
- Crear AccountSettingsController con métodos edit/update limitados al usuario autenticado.
- Crear AccountSettingsUpdateRequest con HasFluentRules y delegar las reglas FluentRule a AccountSettingsUpdateData.
- Crear app/User/Modules/AccountSettings/Data/AccountSettingsUpdateData.php para tipos, reglas y mapeo a atributos de User; construirla desde los datos ya validados y preservar la semántica de campos opcionales.

**validateCommand:**
```text
rtk php -l app/User/Modules/AccountSettings/Controllers/AccountSettingsController.php && rtk php -l app/User/Modules/AccountSettings/Requests/AccountSettingsUpdateRequest.php && rtk php -l app/User/Modules/AccountSettings/Data/AccountSettingsUpdateData.php
```

### TASK-008: Conectar las rutas existentes al nuevo controlador

Mantener las rutas públicas del perfil y mover únicamente el GET/PATCH de ajustes al controlador AccountSettings.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-007  
**Review:** codex

**Acceptance Criteria:**
1. GET /setting/profile y PATCH /setting/profile conservan los nombres profile.edit y profile.update y apuntan a AccountSettingsController.
2. ProfileController conserva profile.index y profile.show, sin manejar la pantalla privada.
3. La ruta profile.update deja de apuntar al método inexistente ProfileController@update y las rutas públicas no cambian.

**writeScope:**
- Actualizar app/User/routes/profile.php para usar AccountSettingsController en las dos rutas de ajustes.
- Retirar edit de app/User/Profile/Controllers/ProfileController.php; conservar index/show.

**validateCommand:**
```text
rtk php artisan route:list --name=profile --except-vendor -vv
```

### TASK-009: Regenerar Wayfinder y actualizar la navegación

Regenerar los helpers después del cambio de controlador y apuntar el sidebar y el menú del perfil al endpoint de AccountSettings.

**Type:** feature
**Effort:** S
**Agent:** react-vite-tailwind-engineer
**Priority:** P1  
**Depends on:** TASK-008  
**Review:** codex

**Acceptance Criteria:**
1. SettingSidebar y el menú del perfil en Home obtienen la URL de edición desde el helper generado de AccountSettingsController.
2. Los helpers profile.edit/profile.update reflejan los URI y métodos existentes.
3. La salida en resources/js/shared/wayfinder es generada por Wayfinder y no se edita a mano.

**writeScope:**
- Actualizar los imports/helpers de edición en SettingSidebar.tsx y homeProfileItems.ts para usar AccountSettingsController.
- Ejecutar php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder --no-interaction y revisar sus cambios generados.

**validateCommand:**
```text
rtk php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder --no-interaction
```

### TASK-010: Registrar pruebas modulares y actualizar ProfileUpdateTest

Mover la cobertura obsoleta al nuevo módulo AccountSettings, convertirla a Pest y registrarla en phpunit.xml y tests/Pest.php.

**Type:** test  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-008  
**Review:** codex

**Acceptance Criteria:**
1. Las pruebas AccountSettings se descubren desde phpunit.xml y reciben TestCase/RefreshDatabase por tests/Pest.php.
2. La prueba cubre persistencia, locale no soportado, bio sobre 160 caracteres, aislamiento entre usuarios y correo no editable.
3. El borrado con contraseña incorrecta conserva la cuenta y el borrado válido usa user.destroy, cierra sesión y elimina la cuenta.

**writeScope:**
- Registrar app/User/Modules/AccountSettings/Tests en phpunit.xml y tests/Pest.php.
- Mover y reescribir tests/Feature/Settings/ProfileUpdateTest.php como Pest en AccountSettings/Tests/Crud/AccountSettingsTest.php.

**Moves:**
- tests/Feature/Settings/ProfileUpdateTest.php -> app/User/Modules/AccountSettings/Tests/Crud/AccountSettingsTest.php

**validateCommand:**
```text
rtk php artisan test --compact app/User/Modules/AccountSettings/Tests/Crud/AccountSettingsTest.php
```

### TASK-011: Probar resumen privado y seguimiento de login

Añadir pruebas modulares que verifiquen timestamps reales, datos aislados y el ciclo correcto del evento Login.

**Type:** test  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-005, TASK-006, TASK-010  
**Review:** codex

**Acceptance Criteria:**
1. El resumen muestra createdAt y lastLoginAt de la cuenta autenticada y no datos de otro usuario.
2. El UserResource público no revela phone, biography, preferredLocale ni lastLoginAt.
3. Login exitoso actualiza el timestamp; credenciales fallidas y challenge 2FA incompleto no lo actualizan.

**writeScope:**
- Crear pruebas del recurso/resumen privado en AccountSettings/Tests/Crud/AccountSettingsSummaryTest.php.
- Crear pruebas del listener en AccountSettings/Tests/Listeners/TrackLastLoginTest.php.

**validateCommand:**
```text
rtk php artisan test --compact app/User/Modules/AccountSettings/Tests/Crud/AccountSettingsSummaryTest.php && rtk php artisan test --compact app/User/Modules/AccountSettings/Tests/Listeners/TrackLastLoginTest.php
```

### TASK-012: Crear la página AccountSettings y el resumen

Mover la página actual al nuevo submódulo frontend y construir el shell de dos columnas con tipos explícitos y datos reales.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-006, TASK-009  
**Review:** codex

**Acceptance Criteria:**
1. La página se resuelve como setting/modules/accountSettings/AccountSettings y usa props locales tipadas.
2. El resumen renderiza name como Usuario, email, createdAt y lastLoginAt desde AccountSettingsResource, sin valores de muestra.
3. El layout se adapta a escritorio/móvil y no muestra una tarjeta de estado inventada.

**writeScope:**
- Mover EditProfile.tsx al nuevo AccountSettings.tsx y componer el shell de dos columnas.
- Crear el tipo de props AccountSettings y el resumen con fechas reales.

**Moves:**
- resources/js/modules/setting/modules/profile/EditProfile.tsx -> resources/js/modules/setting/modules/accountSettings/AccountSettings.tsx

**validateCommand:**
```text
rtk npm exec -- prettier --check resources/js/modules/setting/modules/accountSettings/AccountSettings.tsx resources/js/modules/setting/modules/accountSettings/types/accountSettings.ts resources/js/modules/setting/modules/accountSettings/components/AccountSummaryCard.tsx
```

### TASK-013: Implementar el formulario personal

Crear el formulario accesible de datos personales y conectarlo al endpoint AccountSettings generado por Wayfinder.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-007, TASK-009, TASK-012  
**Review:** codex

**Acceptance Criteria:**
1. Nombre, teléfono opcional, idioma en/es y biografía con contador hasta 160 se inicializan con datos reales y persisten al recargar.
2. El correo está deshabilitado/de solo lectura y no se envía en el request.
3. Errores por campo, procesamiento y confirmación de guardado se anuncian y son utilizables por teclado.

**writeScope:**
- Crear AccountProfileForm.tsx con el Form de Inertia y el helper .form() de Wayfinder.
- Componer el formulario desde AccountSettings.tsx con errores, éxito y estado de procesamiento.

**validateCommand:**
```text
rtk npm exec -- prettier --check resources/js/modules/setting/modules/accountSettings/AccountSettings.tsx resources/js/modules/setting/modules/accountSettings/components/AccountProfileForm.tsx
```

### TASK-014: Añadir las secciones estáticas del diseño

Construir el aviso de privacidad y las preferencias de comunicación solo visuales, y presentar Ver actividad como elemento no operativo.

**Type:** feature  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-012, TASK-013  
**Review:** codex

**Acceptance Criteria:**
1. El aviso y los indicadores de comunicación coinciden con la jerarquía visual del mock y son adaptables a móvil.
2. Ver actividad no tiene href, onClick, diálogo ni solicitud.
3. Los indicadores de comunicación no son controles interactivos y no envían datos.

**writeScope:**
- Crear AccountPrivacyAndPreferences.tsx como sección presentacional.
- Componer el aviso y preferencias estáticas desde AccountSettings.tsx.
- Representar Ver actividad visualmente en AccountSummaryCard sin acción.

**validateCommand:**
```text
rtk npm exec -- eslint resources/js/modules/setting/modules/accountSettings/AccountSettings.tsx resources/js/modules/setting/modules/accountSettings/components/AccountSummaryCard.tsx resources/js/modules/setting/modules/accountSettings/components/AccountPrivacyAndPreferences.tsx
```

### TASK-015: Implementar los diálogos de acciones de cuenta

Convertir el cambio de contraseña en diálogo y mejorar la confirmación de eliminación para explicar consecuencias verificadas y usar el componente PasswordInput existente.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-009, TASK-013, TASK-014  
**Review:** codex

**Acceptance Criteria:**
1. Cambiar contraseña abre un diálogo con contraseña actual, nueva y confirmación, usa PasswordController.update y conserva el aviso de revocación de dispositivos confiables; los errores mantienen abierto el diálogo y el éxito lo cierra y limpia.
2. Los cuatro campos de contraseña entre ambos diálogos usan el PasswordInput compartido con autocomplete apropiado y errores accesibles.
3. El diálogo de eliminación explica que se elimina permanentemente el registro de cuenta y se cierra/invalida la sesión; cualquier afirmación sobre publicaciones, comentarios u otros datos relacionados coincide con los efectos verificados del backend. Una contraseña incorrecta conserva la cuenta.

**writeScope:**
- Reemplazar el enlace de cambio de contraseña en AccountActionsPanel.tsx por el trigger del nuevo diálogo.
- Crear AccountSettingsPasswordDialog.tsx con useDialog, PasswordInput y el endpoint update existente; conservar el aviso sobre revocación de dispositivos.
- Actualizar AccountSettingsDeleteDialog.tsx con consecuencias verificadas y PasswordInput, sin alterar UserController.destroy.

**validateCommand:**
```text
rtk npm exec -- eslint resources/js/modules/setting/modules/accountSettings/components/AccountActionsPanel.tsx resources/js/modules/setting/modules/accountSettings/components/AccountSettingsDeleteDialog.tsx resources/js/modules/setting/modules/accountSettings/components/AccountSettingsPasswordDialog.tsx
```

### TASK-016: Eliminar el GET y la vista independiente de contraseña

Retirar el endpoint de lectura y su página independiente, limpiar la navegación y consumidores antiguos, y conservar el endpoint PUT usado por el diálogo.

**Type:** refactor
**Effort:** S
**Agent:** general-purpose
**Priority:** P1
**Depends on:** TASK-015
**Review:** codex

**Acceptance Criteria:**
1. GET /setting/password deja de estar registrado, EditPassword.tsx y su enlace del sidebar desaparecen, y Wayfinder ya no exporta el helper edit.
2. PUT setting.password.update conserva validación y revocación de dispositivos confiables; el diálogo vuelve a profile.edit tras éxito o error.
3. Las pruebas verifican que el GET no está permitido (405 porque el URI conserva solo el PUT), que el PUT actualiza con credenciales válidas y que una contraseña actual incorrecta no cambia la contraseña ni revoca dispositivos.

**writeScope:**
- Eliminar la ruta GET password.edit de app/Auth/routes/password.php y el método edit de PasswordController, preservando el PUT update.
- Actualizar PasswordUpdateTest.php y PasswordTrustedDeviceRevocationTest.php para usar profile.edit como referer; verificar 404 para el GET antiguo y conservar los casos de éxito/error del PUT.
- Retirar el enlace de contraseña del sidebar y la página EditPassword.tsx; regenerar Wayfinder con la ruta configurada y revisar que solo se quite el helper GET.

**validateCommand:**
```text
rtk php artisan route:list --path=setting/password --except-vendor; rtk php artisan test --compact app/Auth/Modules/Password/Tests/PasswordUpdateTest.php app/Auth/Modules/Password/Tests/PasswordTrustedDeviceRevocationTest.php; rtk npm exec -- eslint resources/js/modules/setting/shared/components/SettingSidebar.tsx
```

### TASK-017: Ajustar al contenido el ancho de los botones de cuenta

Hacer que los botones de perfil público y cambio de contraseña ocupen solo el ancho de su contenido, igual que el botón para eliminar cuenta.

**Type:** feature
**Effort:** S
**Agent:** react-vite-tailwind-engineer
**Priority:** P2
**Depends on:** TASK-016
**Review:** codex

**Acceptance Criteria:**
1. Los botones Ir a mi perfil público y Cambiar contraseña usan el ancho de su contenido y quedan alineados con Eliminar mi cuenta.
2. Los textos descriptivos de las cards conservan todo el ancho disponible y los botones no desbordan en pantallas estrechas.
3. Los botones conservan sus enlaces/diálogo, estados de foco y comportamiento accesible.

**writeScope:**
- Aplicar w-fit max-w-full a los botones de perfil público y cambio de contraseña, siguiendo el botón de eliminación.
- Mantener el contenido descriptivo de cada card con ancho completo para no comprimir el texto al ajustar los botones.

**validateCommand:**
```text
rtk npm exec -- eslint resources/js/modules/setting/modules/accountSettings/components/AccountActionsPanel.tsx resources/js/modules/setting/modules/accountSettings/components/AccountSettingsPasswordDialog.tsx; rtk npm run types
```

### TASK-018: Sincronizar la documentación de arquitectura

Documentar AccountSettings, su diálogo de cambio de contraseña y la ubicación de infraestructura User en las guías fuente del proyecto.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P2  
**Depends on:** TASK-001, TASK-002, TASK-003, TASK-008, TASK-012, TASK-015, TASK-016, TASK-017
**Review:** codex

**Acceptance Criteria:**
1. CLAUDE.md registra AccountSettings, su límite con Auth/Password y perfil público, el uso del diálogo y el patrón Database de User.
2. Las guías backend/frontend documentan las nuevas ubicaciones y explican que Auth/Password conserva la acción update aunque ya no exista la vista independiente.
3. La documentación deja claro que el estado real de la cuenta está diferido a SOC-35 y no forma parte de SOC-34.

**writeScope:**
- Actualizar CLAUDE.md y docs/architecture/backend.md para estructura AccountSettings y User/Database.
- Actualizar docs/architecture/frontend.md para el submódulo setting/accountSettings, diálogo de contraseña y ausencia de la vista independiente.

**validateCommand:**
```text
rtk git diff --check -- CLAUDE.md docs/architecture/backend.md docs/architecture/frontend.md
```

### TASK-019: Ejecutar gates y QA visual de AccountSettings

Validar el slice integrado en escritorio y móvil, la navegación por teclado, contratos de rutas y todos los gates del proyecto.

**Type:** test  
**Effort:** M  
**Agent:** general-purpose  
**Priority:** P1  
**Depends on:** TASK-010, TASK-011, TASK-015, TASK-016, TASK-017, TASK-018
**Review:** codex

**Acceptance Criteria:**
1. Pasan las pruebas AccountSettings y Password, el conjunto completo de Pest, Pint, PHPStan, Rector dry-run seguido de Rector y los gates frontend format/lint/types/build/build:ssr.
2. La comparación manual contra el mock pasa en escritorio y móvil; se recorren con teclado el formulario y los diálogos de cambio de contraseña y eliminación.
3. composer doctor corre como último gate local y no se ejecutan comprobaciones después.

**writeScope:**
**validateCommand:**
```text
rtk vendor/bin/pint --dirty --format agent; rtk composer phpstan; rtk composer rector-dry; rtk composer rector; rtk php artisan test --compact; rtk npm run format:check; rtk npm run lint:check; rtk npm run types; rtk npm run build; rtk npm run build:ssr; QA manual navegador escritorio/móvil/teclado; rtk composer doctor (último gate local, sin checks posteriores).
```

### TASK-020: Registrar el diferimiento del estado real de la cuenta

Registrar la decisión de producto: SOC-34 no implementa ni muestra un estado real de cuenta. La gestión de estados activa/suspendida y su fuente persistida quedan en SOC-35, que solo debe iniciarse cuando haya panel administrativo y roles/permisos. No inferir el estado de email_verified_at, last_login_at ni de la sesión.

**Type:** chore
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P2
**Depends on:** TASK-019
**Review:** codex

**Acceptance Criteria:**
1. La decisión del usuario de diferir el estado real hasta que existan panel administrativo y roles/permisos queda registrada.
2. SOC-35 contiene la semántica, los criterios de acceso y la condición de inicio para esa implementación futura.
3. El alcance de SOC-34 excluye mostrar un estado ficticio y no espera a que SOC-35 se implemente.

**writeScope:**
**validateCommand:**
```text
Sincronizar este plan con SOC-34 y SOC-35; no implementar estados de cuenta en SOC-34.
```

## Failure Modes

- La migración nueva no se descubre porque UserServiceProvider no registra el directorio modular; comprobar migrate:status y migrate --pretend.
- Una nueva propiedad privada se agrega por error a UserResource y aparece en el perfil público; mantener serializadores separados y cubrirlo con prueba.
- Una ruta apunta a ProfileController@update inexistente o Wayfinder conserva un helper obsoleto; route:list y generación Wayfinder son gates explícitos.
- El listener actualiza fecha en un challenge 2FA aún pendiente; probar acceso normal, dispositivo confiable, credenciales erróneas y challenge sin completar.
- Una petición incluye el ID de otra cuenta, email o last_login_at; resolver el objetivo desde el usuario autenticado y rechazar/ignorar campos fuera del contrato.
- La eliminación con contraseña incorrecta cierra la sesión o borra la cuenta; la prueba debe comprobar que la cuenta persiste y el error se muestra.
- El diálogo afirma que publicaciones, comentarios u otros datos relacionados se eliminan sin que el backend o las relaciones/cascadas lo garanticen; limitar el texto a consecuencias verificadas.
- El diálogo de cambio de contraseña pierde el aviso de revocación de dispositivos o no permanece abierto al recibir errores de validación; conservar el contrato del formulario actual.
- La ruta GET de contraseña se elimina sin actualizar los consumidores generados, la navegación y las pruebas que usaban la pantalla antigua.
- Los elementos estáticos parecen toggles activos o el botón de actividad ejecuta una acción accidental; validación manual de interacción.
- El estado de cuenta se presenta como activo sin una fuente aprobada; mantenerlo fuera de SOC-34 y remitir la implementación a SOC-35.

## Ship Cut

- Antes de TASK-019, el feature no está listo para integrar: los gates y QA de pantalla aún faltan.
- TASK-019 valida el alcance acordado de SOC-34, incluidos ambos diálogos; el estado real queda expresamente diferido a SOC-35.
- SOC-34 puede cerrarse con el estado real fuera de alcance; SOC-35 espera a que existan panel administrativo y roles/permisos.

## Test Coverage Map

| Comportamiento | Tarea | Cobertura |
|---|---|---|
| Persistencia, validación, correo de solo lectura y aislamiento entre cuentas | TASK-010 | `app/User/Modules/AccountSettings/Tests/Crud/AccountSettingsTest.php` |
| Datos de resumen reales y privacidad de serialización | TASK-011 | `app/User/Modules/AccountSettings/Tests/Crud/AccountSettingsSummaryTest.php` |
| Último acceso solo tras autenticación exitosa | TASK-011 | `app/User/Modules/AccountSettings/Tests/Listeners/TrackLastLoginTest.php` |
| Borrado válido/inválido con contraseña actual | TASK-010 | `app/User/Modules/AccountSettings/Tests/Crud/AccountSettingsTest.php` |
| Cambio de contraseña desde el diálogo, ausencia del GET antiguo y rechazo de contraseña actual inválida | TASK-016 | `app/Auth/Modules/Password/Tests/PasswordUpdateTest.php` |
| Revocación de dispositivos confiables desde el referer del diálogo y preservación con error de contraseña | TASK-016 | `app/Auth/Modules/Password/Tests/PasswordTrustedDeviceRevocationTest.php` |
| Diseño, responsive y accesibilidad por teclado | TASK-019 | `QA manual de los diálogos contra el mock Linear` |

## Execution Summary

- Tareas: 20.
- Modo: EXPANSION.
- Revisión predeterminada: codex.
- Capas paralelas: [TASK-001, TASK-002, TASK-003] → [TASK-004] → [TASK-005] → [TASK-006] → [TASK-007] → [TASK-008] → [TASK-009, TASK-010] → [TASK-011, TASK-012] → [TASK-013] → [TASK-014] → [TASK-015] → [TASK-016] → [TASK-017] → [TASK-018] → [TASK-019] → [TASK-020].
- Ruta crítica: TASK-003 → TASK-004 → TASK-005 → TASK-006 → TASK-007 → TASK-008 → TASK-009 → TASK-012 → TASK-013 → TASK-014 → TASK-015 → TASK-016 → TASK-017 → TASK-018 → TASK-019 → TASK-020 (registro del diferimiento a SOC-35).

## Task Dependencies

| Tarea | Depends on |
|---|---|
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | — |
| TASK-004 | TASK-001, TASK-003 |
| TASK-005 | TASK-004 |
| TASK-006 | TASK-004, TASK-005 |
| TASK-007 | TASK-006 |
| TASK-008 | TASK-007 |
| TASK-009 | TASK-008 |
| TASK-010 | TASK-008 |
| TASK-011 | TASK-005, TASK-006, TASK-010 |
| TASK-012 | TASK-006, TASK-009 |
| TASK-013 | TASK-007, TASK-009, TASK-012 |
| TASK-014 | TASK-012, TASK-013 |
| TASK-015 | TASK-009, TASK-013, TASK-014 |
| TASK-016 | TASK-015 |
| TASK-017 | TASK-016 |
| TASK-018 | TASK-001, TASK-002, TASK-003, TASK-008, TASK-012, TASK-015, TASK-016, TASK-017 |
| TASK-019 | TASK-010, TASK-011, TASK-015, TASK-016, TASK-017, TASK-018 |
| TASK-020 | TASK-019 |
