# Plan: SOC-30 — Extra: Suite de pruebas para TrustedDevice

**Tipo:** Extra complementario de SOC-30. **Modo:** EXPANSION. **Revisión predeterminada:** codex.

## Overview

Agregar una suite PHPUnit llamada TrustedDevice para ejecutar las pruebas del módulo por nombre, sin incluirlas dos veces en la suite Feature.

## Scope Challenge

phpunit.xml actualmente coloca app/Auth/Modules/TrustedDevice/Tests dentro de Feature junto a tests/Feature. Laravel reenvía opciones de selección a Pest/PHPUnit; `php artisan test TrustedDevice` se interpreta como una ruta y falla, mientras `--testsuite=Unit --list-test-files` confirma que el selector se reenvía. Se confirmó usar el selector estándar `--testsuite=TrustedDevice`, con modo EXPANSION y revisión codex.

## Prerequisites

- El módulo tiene pruebas bajo app/Auth/Modules/TrustedDevice/Tests.
- El proyecto usa PHPUnit 13.3.4, Pest 5.2.1 y Laravel 13.32.0; la configuración vigente es phpunit.xml.

## Non-Goals

- Cambiar pruebas, convenciones Pest, bases de datos de testing ni dependencias.
- Crear un comando Artisan personalizado para aceptar el argumento posicional `TrustedDevice`.

## Contracts

- La suite TrustedDevice apunta a app/Auth/Modules/TrustedDevice/Tests.
- La suite Feature contiene solo tests/Feature, para no registrar dos veces los tests del módulo.
- Comando de selección: php artisan test --testsuite=TrustedDevice --compact; --list-suites muestra TrustedDevice como suite separada.

## Existing Code Leverage

- phpunit.xml ya declara las suites Unit y Feature.
- Laravel TestCommand reenvía las opciones de selección a Pest/PHPUnit.
- php artisan test --testsuite=Unit --list-test-files confirma que Artisan reenvía --testsuite.

## Tasks

### TASK-001: Registrar una suite dedicada para TrustedDevice

**Description:** Mover el directorio de pruebas del módulo fuera de Feature y registrarlo en una suite TrustedDevice para ejecutar el módulo por nombre.

**Type:** chore  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** —

**writeScope:**

- `phpunit.xml`

**Acceptance Criteria:**

- php artisan test --list-suites muestra TrustedDevice como suite separada de Feature.
- php artisan test --testsuite=TrustedDevice --compact ejecuta la suite TrustedDevice y pasa.
- La carpeta del módulo ya no aparece dentro de Feature, evitando pruebas duplicadas en la ejecución global.

**validateCommand:** `php artisan test --list-suites && php artisan test --testsuite=TrustedDevice --compact`

## Failure Modes

- Si la carpeta del módulo sigue también dentro de Feature, la ejecución global puede registrar los mismos tests dos veces.
- Si el nombre de suite o su ruta están mal, --testsuite=TrustedDevice no podrá seleccionar las pruebas del módulo.
- El argumento posicional TrustedDevice se trata como un archivo y no sustituye a --testsuite.

## Ship Cut

Completar cuando PHPUnit liste la suite TrustedDevice, esta ejecute los tests del módulo y la suite Feature deje de incluir ese directorio duplicado.

## Test Coverage Map

- PHPUnit --list-suites valida el registro de TrustedDevice como suite separada.
- php artisan test --testsuite=TrustedDevice --compact ejecuta las pruebas aisladas del módulo.

## Execution Summary

Ruta crítica: TASK-001. Cambio limitado a phpunit.xml; el comando de uso queda como `php artisan test --testsuite=TrustedDevice --compact`.

## Task Dependencies

- TASK-001 → —
