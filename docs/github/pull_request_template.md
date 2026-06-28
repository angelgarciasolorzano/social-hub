# Plantilla de Pull Request

Plantilla que **GitHub carga automáticamente** en la descripción de todo PR nuevo contra este repositorio.

## Ubicación

`.github/PULL_REQUEST_TEMPLATE/pull_request_template.md`

## Cuándo se usa

Al abrir un Pull Request, GitHub rellena el cuerpo del PR con el contenido de este archivo. El autor puede editar la descripción antes de enviar el PR.

## Estructura

La plantilla tiene cuatro secciones principales:

| Sección | Propósito |
|---|---|
| 🚀 Pull Request | Título descriptivo del PR. |
| 📋 Summary | Explica **qué** cambia y **por qué**. |
| ✨ Changes | Lista los cambios concretos (commits, archivos, funcionalidades). |
| 📝 Notes | Contexto adicional: decisiones de diseño, limitaciones conocidas, screenshots. |

> 💡 Si el PR necesita más contexto para los revisores, se les pide usar la [plantilla de review](../github/review_template.md).

## Cómo personalizarla

Edita directamente el archivo `.github/PULL_REQUEST_TEMPLATE/pull_request_template.md`. Los cambios aplican a todos los PRs nuevos de inmediato — no requiere configuración adicional.

La estructura de carpeta (`PULL_REQUEST_TEMPLATE/`) ya está preparada para agregar más plantillas específicas en el futuro:

```
.github/
└── PULL_REQUEST_TEMPLATE/
    ├── pull_request_template.md   ← default
    ├── bug_fix.md
    ├── feature.md
    └── refactor.md
```

GitHub mostrará un selector al abrir el PR para elegir cuál usar cuando existan varios archivos en la carpeta.
