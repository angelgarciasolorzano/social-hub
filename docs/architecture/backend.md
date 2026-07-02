# Arquitectura del Backend

Documento de referencia para crear y mantener módulos del backend en este proyecto Laravel.

## 1. Convención de nomenclatura

Todos los archivos y clases usan **PascalCase** y empiezan con el nombre del módulo.

| Tipo | Patrón | Ejemplo |
|---|---|---|
| Controller | `{Module}Controller` | `PostController` |
| Model | `{Module}` | `Post` |
| Factory | `{Module}Factory` | `PostFactory` |
| Request | `{Module}{Action}Request` | `PostStoreRequest`, `PostUpdateRequest` |
| Resource | `{Module}Resource` | `PostResource` |
| Collection | `{Module}Collection` | `CommentCollection` |
| Seeder | `{Module}Seeder` | `PostSeeder` |
| Enum | `{Module}{Concept}` | `CommentableType`, `FriendshipStatus` |
| Service Provider | `{Module}ServiceProvider` | `PostServiceProvider` |
| Route Service Provider | `{Module}RouteServiceProvider` | `PostRouteServiceProvider` |
| Policy | `{Module}Policy` | `PostPolicy` |
| Service | `{Module}Service` | `PostService` |
| Console Command | `{Module}{Purpose}Command` | `MediaLibraryCleanFoldersCommand` |

**Excepción:** `routes/routes.php` no lleva prefijo (Laravel lo busca así).

## 2. Estructura de un módulo

```
app/<Module>/
├── Controllers/
│   └── {Module}Controller.php
├── Models/
│   └── {Module}.php
├── Requests/
│   ├── {Module}StoreRequest.php
│   └── {Module}UpdateRequest.php
├── Resources/
│   ├── {Module}Resource.php
│   └── {Module}Collection.php        ← solo si hay listado
├── Factories/
│   └── {Module}Factory.php
├── Seeders/
│   └── {Module}Seeder.php            ← solo si hay datos iniciales
├── Providers/
│   ├── {Module}ServiceProvider.php
│   └── {Module}RouteServiceProvider.php
└── routes/
    └── routes.php
```

**No todo módulo necesita todo.** Solo crea las carpetas que vayas a usar. El núcleo obligatorio:

- `Models/`
- `Providers/` (ambos providers)
- `routes/`

## 3. Submódulos

Cuando un módulo tiene **varias áreas independientes** que ameritan su propio espacio, se subdivide. Ejemplos del proyecto:

- `app/Auth/` → `Email/`, `Login/`, `Password/`, `Register/`
- `app/User/` → `Profile/`, `Preferences/`, `TwoFactorAuthentication/`

### Cuándo subdividir

| ✅ Sí | ❌ No |
|---|---|
| Hay flujos independientes (login vs registro) | Es un módulo pequeño y cohesivo |
| Cada área tiene sus propias Requests con validaciones distintas | Los archivos están muy acoplados |
| Las áreas evolucionarán por separado | Solo tienes 2-3 archivos |

### Estructura de un submódulo

```
app/<Module>/<Submodule>/
├── Controllers/
│   └── <Submodule>Controller.php
└── Requests/                          ← solo si hay validaciones específicas
    └── <Submodule>Request.php
```

**Importante:** los submódulos NO tienen providers propios. Las rutas se centralizan en `app/<Module>/routes/` con un archivo por submódulo:

```
app/Auth/routes/
├── routes.php        ← carga los includes
├── email.php
├── login.php
├── password.php
└── register.php
```

## 4. Responsabilidad de cada archivo

| Tipo | Qué hace |
|---|---|
| **Controller** | Orquesta HTTP: recibe Request → llama al modelo → retorna Response |
| **Model** | Entidad del dominio + relaciones Eloquent |
| **Request** | Valida input del usuario (FormRequest con FluentRule) |
| **Resource** | Transforma un modelo al JSON que verá el cliente |
| **Factory** | Genera instancias falsas para tests/seeders |
| **Seeder** | Puebla la BD con datos iniciales |
| **Policy** | Reglas de autorización (quién puede hacer qué) |
| **Service** | Lógica de negocio compleja que no encaja en un controller |
| **Console Command** | Operaciones Artisan: batch, mantenimiento, sincronización |
| **Service Provider** | Registra bindings en el contenedor de Laravel |
| **Route Service Provider** | Carga las rutas del módulo bajo middleware `web` |

> 💡 Si una acción no cabe en un controller (muchos casos, reglas complejas, transacciones múltiples) → crea un **Service**. Si una autorización es reutilizable → crea una **Policy**.

## 5. Cómo crear un módulo nuevo

Sigue esta checklist en orden:

1. **Crear la estructura de carpetas:**
   ```bash
   mkdir -p app/{Module}/{Models,Providers,routes,Controllers,Requests,Resources}
   ```

2. **Crear el modelo** en `app/{Module}/Models/{Module}.php`

3. **Crear la factory** en `app/{Module}/Factories/{Module}Factory.php`

4. **Crear los dos providers:**
   - `app/{Module}/Providers/{Module}ServiceProvider.php` — su `boot()` registra el `RouteServiceProvider`
   - `app/{Module}/Providers/{Module}RouteServiceProvider.php` — extiende `Illuminate\Foundation\Support\Providers\RouteServiceProvider`, carga `app/{Module}/routes/routes.php` con middleware `web`

5. **Registrar el `{Module}ServiceProvider`** en `bootstrap/providers.php`

6. **Crear el controller** en `app/{Module}/Controllers/{Module}Controller.php`

7. **Crear las Requests** según las acciones (Store, Update, u otras específicas)

8. **Crear la Resource** (y Collection si hay listado)

9. **Definir las rutas** en `app/{Module}/routes/routes.php`

10. **Tests feature** en `tests/Feature/{Module}/`

## 6. Convenciones adicionales

- Todo archivo PHP empieza con `declare(strict_types=1);`
- Namespaces PSR-4: `App\{Module}\{Type}`
- Relaciones Eloquent declaran genéricos en PHPDoc: `@return HasMany<Post, $this>`
- Models con `HasFactory` declaran el genérico: `/** @use HasFactory<{Module}Factory> */`
- Constantes del modelo (MORPH_NAME, MORPH_COLUMN) llevan docblock descriptivo
- Si el módulo es polimórfico, registra el morph en `App\Providers\AppServiceProvider::boot()` (`Relation::enforceMorphMap`)

## Ejemplo canónico: módulo `Post`

```
app/Post/
├── Controllers/PostController.php
├── Factories/PostFactory.php
├── Models/Post.php
├── Providers/
│   ├── PostServiceProvider.php
│   └── PostRouteServiceProvider.php
├── Requests/PostRequest.php
├── Resources/PostResource.php
├── Seeders/PostSeeder.php
└── routes/routes.php
```

Usa este módulo como referencia al crear nuevos.
