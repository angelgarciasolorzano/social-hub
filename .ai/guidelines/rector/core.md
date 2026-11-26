# Rector

- Los agentes de IA DEBEN ejecutar `composer rector-clear-cache`, después `composer rector-dry` y después `composer rector` en toda tarea que modifique archivos PHP, aunque el cambio no sea un refactor grande.
- Limpia la caché de Rector (`composer rector-clear-cache`) antes de `composer rector-dry`: la caché local (`storage/rector`) puede dar un resultado limpio mientras el CI sí marca cambios. Ejecuta Rector sobre todo el proyecto, sin rutas parciales, para que las reglas de renombrado resuelvan los tipos de otros archivos.
- `composer rector` es un gate obligatorio y debe aplicarse automáticamente después de revisar el resultado de `composer rector-dry`; no requiere aprobación adicional del usuario.
