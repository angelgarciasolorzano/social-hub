# Auditoría de las suites de contenido: Comment, Like y Post (TASK-006)

Alcance: los 31 archivos de `app/Modules/Comment/Tests` (17), `app/Modules/Like/Tests` (6) y `app/Modules/Post/Tests` (8), unos 330 casos. Mismo criterio que en `audit-admin.md`: solo se elimina un caso cuando otro detecta exactamente el mismo fallo y se cita cuál; no se fusionan comportamientos distintos, por la regla "Keep backend tests focused" de `.ai/rules/backend.md`.

## Resumen

| Tipo | Hallazgos | Casos afectados |
| --- | --- | --- |
| Redundante (se elimina) | R1–R13 | 23 casos |
| Setup innecesario (se simplifica) | S1–S2 | 2 archivos |
| Revisado y conservado | C1–C8 | — |

## Patrón principal: validación probada dos veces

Los FormRequest tienen tests directos con `FluentRulesTester` (`CommentStoreRequestTest`, `CommentUpdateRequestTest`, `PostRequestTest`). Los tests HTTP de CRUD repiten esas mismas reglas contra la ruta y además comprueban que no se guardó nada. Ese "no se guardó nada" no es lógica del proyecto: Laravel corta la petición antes del controlador cuando el FormRequest falla. Basta con **un** caso HTTP por endpoint para probar que el request está conectado a la ruta. El resto de las reglas (límites, tipos, mensajes) quedan en los tests del request, que no necesitan base de datos.

## Redundantes

| Id | Test a eliminar | Lo cubre | Motivo |
| --- | --- | --- | --- |
| R1 | `Comment/Tests/Crud/CommentStoreTest.php` · "rejects content longer than 1000 characters", "accepts content of exactly 1000 characters", "rejects an unsupported commentable type" y "rejects a non-integer commentable id" | `Requests/CommentStoreRequestTest.php` · "accepts content of exactly 1000 characters and rejects 1001", "only accepts the post and comment commentable types" y "requires an integer commentable id"; la conexión ruta-request la conserva "requires the content of the comment" | Las mismas reglas en otra capa. 4 casos. |
| R2 | `Comment/Tests/Crud/CommentUpdateTest.php` · casos 'only whitespace' y '1001 characters' del dataset de "rejects an empty or too long content and leaves the comment untouched" | `Requests/CommentUpdateRequestTest.php` · "rejects content made only of control characters or whitespace" y "accepts content of exactly 1000 characters and rejects 1001"; el caso 'empty' del dataset se queda como prueba de conexión y de que `edited_at` no cambia | 2 casos. |
| R3 | `Comment/Tests/Models/CommentModelTest.php` · "has no replied to user when it does not mention anyone", "has no edited date when it is created" y "still finds a deleted comment when trashed comments are included" | `Factories/CommentFactoryTest.php` · "does not mention anyone by default", "is neither edited nor deleted by default" y "marks the comment as deleted with the trashed state" (que ya busca con `withTrashed()`) | El primero prueba el `BelongsTo` de Eloquent con clave nula; los otros dos repiten aserciones de la factory sobre el mismo `createComment()`. La decisión de usar `SoftDeletes` la sigue fijando "keeps a deleted comment out of the default queries". 3 casos. |
| R4 | `Comment/Tests/Resources/CommentResourceTest.php` · "keeps the cursor metadata when a page of comments is serialized through the resource" y "reports no further pages when everything fits in one" | `Props/CommentThreadPropsTest.php` · "serves the next page from the comments cursor and ends without a further cursor" y "serializes each page as a plain cursor paginator envelope with its own cursor name" | Los dos primeros paginan con un `cursorPaginate()` construido en el propio test (`commentsCursorPage()`), no con el código de producción, así que prueban el `CursorPaginator` de Laravel. Los de props prueban el paginador real. 2 casos; `commentsCursorPage()` queda sin uso y se borra. |
| R5 | `Comment/Tests/Props/CommentThreadPropsTest.php` · "allows 60 loads per minute and then answers 429" | `Security/CommentThrottleTest.php` · "allows 60 listings per minute and then answers 429" | Mismo presupuesto (`comments.index`, 60 por minuto); el de Throttle lo prueba de punta a punta por HTTP. |
| R6 | `Comment/Tests/Props/CommentThreadPropsTest.php` · "does not let reading comments consume the comment publishing budget" | `Security/CommentThrottleTest.php` · "does not let listing comments consume the comment publishing budget" | El mismo test, con el mismo nombre salvo una palabra, en los dos archivos. |
| R7 | `Comment/Tests/Props/CommentThreadPropsTest.php` · "uses the same number of queries whether the thread has one or many comments and replies" | `Security/CommentQueryCountTest.php` · "uses the same number of queries regardless of how many comments or replies are listed" | Mismo escenario (10 comentarios, cada uno con una respuesta y un like, contra un post con uno); el de QueryCount lo mide en la petición real y tiene además variantes con editados, eliminados y menciones. |
| R8 | `Comment/Tests/Props/CommentThreadPropsTest.php` · "keeps listing the comments of a post newest first" | Mismo archivo · "lists the comments of a post ten at a time in descending id order with their own cursor" | Ese test ya exige que los ids vengan en orden descendente. |
| R9 | `Comment/Tests/Security/CommentThrottleTest.php` · "throttles the reading of comments per user" | `Props/CommentThreadPropsTest.php` · "shares the read budget between comments and replies but keeps it per user" | Los dos agotan el presupuesto de un usuario y comprueban que otro sigue leyendo; el de props además prueba que comentarios y respuestas comparten el presupuesto. Es uno de los tests más lentos de la suite (0,43 s, 61 peticiones HTTP). |
| R10 | `Comment/Tests/Security/CommentSecurityAuditTest.php` · "[H7] does not expose private user data in the serialized comments" y "[H3] strips control characters and trims the content of a reply to a reply" | `Resources/CommentResourceTest.php` · "serializes only the public comment fields" y "exposes only the id and name of the author" (listas exactas: no puede aparecer el email ni otro campo); `Security/CommentValidationHardeningTest.php` · "stores the content sanitized: :dataset" | El saneamiento ocurre en `prepareForValidation()` del request, igual para un post que para una respuesta a una respuesta. 2 casos. |
| R11 | `Comment/Tests/Seeders/CommentSeederTest.php` · "never deletes a reply" | Mismo archivo · "only deletes root comments that have replies" (cada comentario eliminado debe ser de tipo post) junto con "leaves some root comments with replies as deleted tombstones" (garantiza que hay eliminados) | Implicado por los otros dos. 0,74 s en la línea base. |
| R12 | `Like/Tests/Seeders/LikeSeederTest.php` · "never repeats a user on the same comment" | `Like/Tests/Models/LikeUniquenessTest.php` · "rejects a second like by the same user on the same comment" | El índice único `(user_id, likeable_type, likeable_id)` impide los likes repetidos a nivel de base, así que este test no puede fallar. La idempotencia del seeder (`insertOrIgnore`) la sigue probando "does not fail nor repeat likes when it runs twice". |
| R13 | `Post/Tests/Crud/PostStoreTest.php` · "rejects content shorter than 10 characters", "rejects an uploaded file that is not a png, jpg or webp image" y "rejects an uploaded image larger than 5MB" | `Requests/PostRequestTest.php` · "rejects content shorter than 10 characters with a custom message", "rejects files that are not png, jpg or webp images" y "rejects images larger than 5MB with a custom message" (con los mismos mensajes) | Mismo patrón que R1; la conexión la conserva "requires the content of the post". "flashes an error but keeps the post when the image is too big" se queda: prueba el límite de MediaLibrary en el controlador, no la validación. 3 casos. |

En la tabla, R1–R13 suman 4 + 2 + 3 + 2 + 1 + 1 + 1 + 1 + 1 + 2 + 1 + 1 + 3 = **23 casos**.

## Setup innecesario

- **S1** — `Post/Tests/Resources/PostResourceTest.php` · "returns an empty image when the post has no media" ya no corre el riesgo de recibir una imagen aleatoria de la factory (TASK-003); no necesita cambios, pero confirma que el fallo intermitente que tenía en local desapareció. `PostSeederTest` siembra con `withImage(60)` sin `Storage::fake('public')`: en local copia imágenes reales al disco público. Agregar `Storage::fake('public')` en su helper `seedPostCountsForUsers()` y en los dos tests que siembran sin él.
- **S2** — `CommentSeederTest` repite la siembra completa (de 8 a 60 posts) en cada uno de sus 18 casos; son propiedades distintas y se conservan, pero los tests de borrado usan 60 posts solo para que aparezca al menos un tombstone con la semilla fija. Bajar ese número sin perder el tombstone queda como optimización opcional, después de medir.

## Revisado y conservado

- **C1 — Seguridad de comentarios**: "[H3] rejects whitespace-only content" (el request de creación no tiene ese caso en sus tests directos), "[H3] never renders comments as raw HTML", "[H8]" de rutas expuestas, `user_id` y `replied_to_user_id` falsificados. "[H8] never nests replies deeper than one level when replying in a chain" se parece a "attaches a reply to a reply to the parent comment" de `CommentStoreTest`, pero prueba el invariante en cadena; se conserva por ser un límite de seguridad sin duplicación exacta.
- **C2 — Throttles**: publicar, editar, borrar y dar like tienen presupuestos separados y cada test de "no comparte presupuesto" protege un prefijo distinto. "lets the user … again once the minute has passed" fija la ventana de cada límite.
- **C3 — Tests de request**: son la fuente de las reglas tras R1, R2 y R13.
- **C4 — Props y páginas anfitrionas**: `CommentThreadHostPagesTest` corre cada caso sobre las tres páginas (home, perfil propio y perfil ajeno) que inyectan las props; prueba la conexión de cada página, no las reglas de props.
- **C5 — Conteos de consultas** (`CommentQueryCountTest`, `PostListingTest`, `PostModelTest` "preloads the engagement of many posts in a single query", los de `CommentResourceTest` "without running queries").
- **C6 — Toggles de like**: los de posts y comentarios son endpoints distintos. "likes the post/comment of the user itself" fija la decisión de que se puede dar like a lo propio (CLAUDE.md, SOC-51).
- **C7 — `LikeModelTest` "refuses to resolve a likeable type missing from the morph map"**: es el único test del proyecto que fija `Relation::enforceMorphMap()`; los de cada modelo solo comprueban su propia clave.
- **C8 — Seeders**: cada propiedad (límites, variedad, autores, menciones, editados, tombstones) puede fallar por separado.

## Propuesta de tareas para TASK-009 (máximo 3 archivos cada una)

1. R1, R2 — `CommentStoreTest.php` y `CommentUpdateTest.php`.
2. R3, R4 — `CommentModelTest.php` y `CommentResourceTest.php`.
3. R5–R9 — `CommentThreadPropsTest.php` y `CommentThrottleTest.php`.
4. R10, R11 — `CommentSecurityAuditTest.php` y `CommentSeederTest.php`.
5. R12, R13, S1 — `LikeSeederTest.php`, `PostStoreTest.php` y `PostSeederTest.php`.
