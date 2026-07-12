# Arquitectura del Frontend

Documento de referencia para crear y mantener módulos del frontend en este proyecto React + Inertia v3.

> ⚠️ **Mantenimiento de la doc**: cuando agregues, modifiques o elimines una convención (o cualquier otro cambio de arquitectura del frontend), actualizá este documento en el mismo PR/commit. La doc es la fuente de verdad de las convenciones del proyecto y no debe quedar desincronizada con el código.

## 1. Convención de nomenclatura

| Tipo                         | Patrón                                           | Ejemplo                                                     |
| ---------------------------- | ------------------------------------------------ | ----------------------------------------------------------- |
| Página / vista               | `PascalCase.tsx`                                 | `TwoFactorDisabled.tsx`, `Login.tsx`                        |
| Componente de feature        | `PascalCase.tsx`                                 | `PostCard`, `CommentDialog`, `TwoFactorSetupDialog`         |
| Sub-componente de paso       | `PascalCaseStep.tsx`                             | `ChooseMethodStep`, `VerifyOtpStep`, `TwoFactorSuccessStep` |
| Hook                         | `useCamelCase.ts`                                | `useTwoFactorAuth`, `useClipboard`, `useDialog`             |
| Tipo / interface             | `PascalCase`                                     | `Comment`, `TwoFactorSetupDialogProps`                      |
| Enum (const + tipo derivado) | `PascalCase` (const) / `PascalCaseValues` (tipo) | `CommentableType` → `CommentableTypeValues`                 |
| Tipo unión con const-as-keys | `{concept}Key` (const) / `PascalCase` (tipo)     | `twoFactorActivationStepKey` → `TwoFactorActivationStep`    |
| Utilidad                     | `camelCase.ts`                                   | `downloadRecoveryCodes`, `colorHoverMap`                    |
| Datos estáticos / fixtures   | `camelCase.ts`                                   | `twoFactorDisabled`, `twoFactorEnable`                      |
| Layout                       | `PascalCaseLayout.tsx`                           | `AuthCardLayout`                                            |

## 2. Estructura general de `resources/js/`

```
resources/js/
├── app.tsx                                ← entrypoint Inertia + React
├── modules/                               ← features por dominio
│   ├── auth/
│   ├── comments/
│   ├── home/
│   ├── post/
│   ├── profile/
│   ├── setting/
│   └── static/
└── shared/                                ← código reutilizable
    ├── assets/                            ← imágenes, íconos SVG, etc.
    ├── components/
    │   ├── AlertError.tsx
    │   ├── TextLink.tsx
    │   ├── form/                          ← inputs, labels, errores
    │   ├── logo/                          ← logos y brand
    │   ├── reactBits/                     ← animaciones / efectos
    │   └── shadcn/ui/                     ← primitivas shadcn (no editar a mano)
    ├── hooks/                             ← hooks genéricos (useDialog, useClipboard, …)
    ├── lib/                               ← utilidades (cn, validImage, …)
    ├── types/                             ← tipos compartidos (User, PaginatedResponse, …)
    └── wayfinder/                         ← autogenerado por Vite (no editar)
```

**Reglas clave:**

- **Alias `@/`** apunta a `resources/js/`. Usalo en todos los imports (`@/shared/...`, `@/modules/...`).
- **No edites** lo que esté bajo `shared/wayfinder/` — se regenera por el plugin de Vite.
- **No edites** lo que esté bajo `shared/components/shadcn/ui/` salvo que sea para actualizar shadcn. Para wrappers propios, creá un componente nuevo en `shared/components/` o en el módulo.

## 3. Estructura de un módulo

Un módulo representa un **dominio de feature** y vive en `resources/js/modules/<domain>/`. La estructura típica:

```
resources/js/modules/<domain>/
├── index.ts                               ← barrel export (público)
├── assets/                                ← imágenes y SVGs del módulo
├── components/                            ← componentes de UI del feature
│   ├── <Feature>Card.tsx
│   ├── <Feature>Dialog.tsx
│   └── forms/                             ← formularios específicos
├── data/                                  ← fixtures, arrays de UI (no lógica)
├── enums/                                 ← const-as-keys enums del módulo
├── hooks/                                 ← hooks específicos del feature
├── types/                                 ← tipos e interfaces del módulo
├── utils/                                 ← utilidades puras del módulo
├── views/                                 ← páginas del módulo (si las hay)
└── layouts/                               ← layouts compartidos del módulo (raro)
```

**No todo módulo necesita todo.** Creá solo las carpetas que vayas a usar.

### Barrel export (`index.ts`)

Cada módulo expone sus componentes públicos vía `index.ts`:

```ts
// resources/js/modules/post/index.ts
export { default as PostCard } from "./components/PostCard";
export { default as PostDetail } from "./components/PostDetail";
export { default as PostDialog } from "./components/PostDialog";
export { default as postPlaceholder } from "./assets/post-placeholder.png";
export * from "./types/post";
```

Y se consume desde otros módulos así:

```ts
import { type Post, PostCard } from "@/modules/post";
```

## 4. Submódulos

Cuando un módulo tiene **varias áreas grandes e independientes**, se subdivide. Ejemplos del proyecto:

- `resources/js/modules/setting/modules/twoFactor/`
- `resources/js/modules/setting/modules/profile/`
- `resources/js/modules/setting/modules/password/`
- `resources/js/modules/setting/modules/preference/`

### Cuándo subdividir

| ✅ Sí                                              | ❌ No                                |
| -------------------------------------------------- | ------------------------------------ |
| El área tiene su propio estado, hooks y tipos      | Es solo un componente más del módulo |
| Las áreas evolucionan por separado                 | Hay 2-3 archivos chicos              |
| Tiene sub-pantallas completas (no es solo un form) | Los archivos están acoplados         |

### Estructura de un submódulo

```
resources/js/modules/<parent>/<submodule>/
├── components/
│   ├── dialog/                            ← modales del submódulo
│   │   └── <Submodule>Dialog.tsx
│   │       └── steps/                     ← un archivo por paso del flujo
│   │           ├── <Step1>Step.tsx
│   │           ├── <Step2>Step.tsx
│   │           └── <StepN>Step.tsx
│   └── ui/                                ← piezas reutilizables del submódulo
├── data/                                  ← fixtures específicos
├── hooks/                                 ← hooks del submódulo
├── types/                                 ← tipos y const-as-keys
├── utils/                                 ← utilidades del submódulo
└── views/                                 ← vista principal del submódulo
```

**No hay routing de frontend** en sí: las rutas las maneja el backend (Laravel) y se exponen via Wayfinder. Los "submódulos" son puramente una **agrupación de carpetas** para mantener cohesión.

## 5. Convenciones de tipos

### 5.1. Enums con **const-as-keys**

**Siempre** que necesites un set finito de valores identificados (estados, tipos, categorías), usá el patrón **const-as-keys**. Es la fuente única de verdad: el `const` declara los valores y el `type` se deriva automáticamente.

```ts
// resources/js/modules/comments/enums/commentableType.ts
export const CommentableType = {
  COMMENT: "comment",
  POST: "post",
} as const;

export type CommentableTypeValues = (typeof CommentableType)[keyof typeof CommentableType];
```

Uso en `switch`, `setState`, comparaciones:

```tsx
// switch con autocomplete y exhaustiveness implícita
switch (kind) {
  case CommentableType.COMMENT: ...
  case CommentableType.POST: ...
}

// setState seguro (no permite strings fuera del union)
setKind(CommentableType.POST);

// comparación sin typos
if (kind === CommentableType.COMMENT) { ... }
```

**Beneficios:**

- **Refactor seguro**: renombrar un valor en el `const` se propaga a todos los call-sites.
- **Autocomplete IDE**: `case CommentableType.` te sugiere los valores.
- **Exhaustiveness implícita**: si la función no retorna `undefined` y agregás un valor al `const`, TS marca error en los `switch` que olviden un `case`.

### 5.2. Tipo unión con const-as-keys para state machines

Cuando un componente tiene varios pasos discretos (wizard, modal flow, etc.), declaralo como unión tipada y derivá un `const` para los valores:

```ts
// resources/js/modules/setting/modules/twoFactor/types/twoFactorActivationStep.ts
export const twoFactorActivationStepKey = {
  chooseMethod: "chooseMethod",
  manualSetup: "manualSetup",
  verifyingOTP: "verifyingOTP",
  success: "success",
} as const;

export type TwoFactorActivationStep =
  (typeof twoFactorActivationStepKey)[keyof typeof twoFactorActivationStepKey];

export const DEFAULT_TWO_FACTOR_ACTIVATION_STEP: TwoFactorActivationStep =
  twoFactorActivationStepKey.chooseMethod;
```

Después en el `switch`:

```tsx
switch (step) {
  case twoFactorActivationStepKey.chooseMethod: return <ChooseMethodStep {...} />;
  case twoFactorActivationStepKey.manualSetup:  return <ManualSetupStep  {...} />;
  case twoFactorActivationStepKey.verifyingOTP: return <VerifyOtpStep    {...} />;
  case twoFactorActivationStepKey.success:      return <TwoFactorSuccessStep {...} />;
}
```

**Evitá** booleanos sueltos (`isStep1`, `isStep2`, ...) para representar estados — escalan mal y son propensos a estados intermedios inválidos. Usá un único `useState<TwoFactorActivationStep>`.

### 5.3. Props tipadas con `interface`

Los props de cada componente se declaran con `interface Props` (no `type`) y se exportan solo si se reutilizan:

```ts
interface TwoFactorSetupDialogProps {
  clearSetupData: () => void;
  errors: string[];
  isOpen: boolean;
  onClose: () => void;
}
```

### 5.4. Tipos derivados con `Pick<>` y `Omit<>`

Para tipos parciales (ej. "Comment pero solo content + id") preferí los utility types sobre redeclarar:

```ts
export type CommentFormData = Pick<Comment, "content"> & {
  commentable_id: number;
  commentable_type: CommentableTypeValues;
};
```

## 6. Convenciones de hooks

### 6.1. Custom hook para flujos con estado

Cuando un componente maneja un flujo de varios pasos, **extraé la lógica a un custom hook** y dejá el componente solo con el render. Esto:

- Separa **lógica** (state + transiciones) de **vista** (JSX).
- Hace el hook testeable de forma aislada.
- Mantiene el componente del dialog corto y legible.

**Regla de capas del hook:**

| ✅ En el hook                                     | ❌ Fuera del hook (en el componente)        |
| ------------------------------------------------- | ------------------------------------------- |
| `useState`, `useEffect`, `useMemo`, `useCallback` | `renderStep()` con JSX                      |
| Transiciones (`handleX`, `goToY`)                 | `DialogHeaderIcon` y otros helpers visuales |
| `modalConfig` (datos: título + descripción)       | Botones / wrappers con JSX                  |
| `useEffect` de setup data y reset                 |                                             |

```ts
// hooks/useTwoFactorActivationFlow.ts
export function useTwoFactorActivationFlow(
  params: UseTwoFactorActivationFlowParams,
): UseTwoFactorActivationFlowReturn {
  const { step, modalConfig, handleChooseMethodContinue, ... } = ...;
  return { step, modalConfig, handleChooseMethodContinue, ... };
}
```

El componente consume el hook y renderiza:

```tsx
const { step, modalConfig, renderStep, handleClose } = useTwoFactorActivationFlow({...});

return (
  <Dialog open={isOpen} onOpenChange={(open) => !open && handleClose()}>
    <DialogContent>
      <DialogHeader><DialogTitle>{modalConfig.title}</DialogTitle></DialogHeader>
      {renderStep()}
    </DialogContent>
  </Dialog>
);
```

### 6.2. Hook de visibilidad de diálogos (`useDialog`)

Para abrir/cerrar un `<Dialog>` de shadcn/ui, **usá el hook genérico `useDialog`** de `@/shared/hooks`. No crees un hook específico por feature que duplique la misma lógica.

```ts
// resources/js/shared/hooks/useDialog.ts
export function useDialog(initialState: boolean = false): UseDialogReturn {
  const [open, setOpen] = useState(initialState);
  return { open, setOpen };
}
```

Uso (renombrando localmente para mayor claridad):

```ts
const { open: showSetupModal, setOpen: setShowSetupModal } = useDialog();
```

**Cuándo crear un hook propio:** **nunca** para visibilidad de dialogs. `useDialog` ya encapsula el `useState<boolean>` y se puede llamar múltiples veces en el mismo componente para manejar varios dialogs independientes. No crees `useFooDialog` ni similares..

### 6.3. Hooks de feature con un solo return tipado

Cada hook de feature (ej. `useTwoFactorAuth`) declara un `type UseXxxReturn` y devuelve **un solo objeto** con todos los valores y callbacks:

```ts
// El type NO se exporta — solo lo consume la firma del hook dentro del archivo.
type UseTwoFactorAuthReturn = {
  qrCodeSvg: string | null;
  manualSetupKey: string | null;
  recoveryCodesList: string[];
  fetchSetupData: () => Promise<void>;
  // ...
};

export const useTwoFactorAuth = (): UseTwoFactorAuthReturn => {
  // ...
  return { qrCodeSvg, manualSetupKey, recoveryCodesList, fetchSetupData, ... };
};
```

**Regla de visibilidad de tipos:**

- ✅ **Exportar** solo tipos que se consumen **fuera del archivo** (ej. enums, tipos de props compartidas, tipos de modelo de dominio).
- ❌ **NO exportar** el `type UseXxxReturn` ni similares que solo existen para tipar la firma del propio hook. Mantenerlos como type/interface internos (sin `export`) reduce el surface area del módulo y deja claro que son detalles de implementación.

Ejemplos válidos del proyecto:

| Hook                         | Tipo interno                       | Exportado al exterior                                     |
| ---------------------------- | ---------------------------------- | --------------------------------------------------------- |
| `useTwoFactorAuth`           | `UseTwoFactorAuthReturn`           | ❌ (solo firma)                                           |
| `useTwoFactorActivationFlow` | `UseTwoFactorActivationFlowReturn` | ❌ (solo firma)                                           |
| `useAppearance`              | `UseAppearanceReturn`              | ❌ (solo firma)                                           |
| `useAppearance`              | `Appearance`, `ResolvedAppearance` | ✅ (consumidos en `Appearance.tsx`, `appearanceItems.ts`) |

### 6.4. Hooks de UI encapsulando comportamiento + estado

Cuando un componente combina **estado local + efectos + UI** (ej. copiar al portapapeles con countdown y barra de progreso), extraé esa lógica a un hook de feature. El componente queda con **solo render** y el hook es **testeable de forma aislada**.

**Cuándo aplica:**

- Hay un `useState` + `useEffect` + `useCallback` coordinados en el componente.
- La lógica es reutilizable potencialmente (puede que otro feature necesite "copy con feedback temporal").
- Sacarla del componente mejora la legibilidad (componente >150 líneas con lógica intercalada).

**Ejemplo del proyecto — `useCopyWithCountdown`:**

```ts
// resources/js/modules/setting/modules/twoFactor/hooks/useCopyWithCountdown.ts

interface UseCopyWithCountdownParams {
  durationMs: number;
  tickMs: number;
}

interface UseCopyWithCountdownReturn {
  copiedText: string | null;
  copy: (text: string) => void;
  isActive: boolean;
  progressPercent: number;
  secondsLeft: number;
}

export function useCopyWithCountdown({
  durationMs,
  tickMs,
}: UseCopyWithCountdownParams): UseCopyWithCountdownReturn {
  // Encapsula: useClipboard + countdown + intervalo + cleanup
  // ...
}
```

El componente consume el hook y queda enfocado en el render:

```tsx
function ManualSetupStep({ manualSetupKey }: Props) {
  const { copiedText, copy, isActive, progressPercent, secondsLeft } = useCopyWithCountdown({
    durationMs: 10_000,
    tickMs: 1_000,
  });

  const isCopied = copiedText !== null && copiedText === manualSetupKey;

  return (
    <>
      <Button onClick={() => manualSetupKey && copy(manualSetupKey)}>
        {isCopied ? <Check /> : <Copy />}
      </Button>

      {isActive && (
        <CopyFeedbackAlert progressPercent={progressPercent} secondsLeft={secondsLeft} />
      )}
    </>
  );
}
```

**Convención de ubicación:**

| Tipo de hook                              | Ubicación                                                     |
| ----------------------------------------- | ------------------------------------------------------------- |
| Hook genérico (sin dominio de negocio)    | `resources/js/shared/hooks/useXxx.ts`                         |
| Hook de feature (específico de un módulo) | `resources/js/modules/<domain>/hooks/useXxx.ts`               |
| Hook de submódulo                         | `resources/js/modules/<parent>/modules/<sub>/hooks/useXxx.ts` |

**Diferencia con §6.1 (`useTwoFactorActivationFlow`):**

- §6.1 — **State machine**: hook que coordina transiciones entre pasos (`step: "chooseMethod" | "verifyingOTP" | …`).
- §6.4 — **Comportamiento + UI**: hook que encapsula un efecto concreto reutilizable (copy, drag, debounce, etc.) con su estado derivado.

## 7. Convenciones de componentes

### 7.1. Componentes de paso (steps) para flujos modales

Cuando un modal tiene varios pasos visuales, cada paso es **un componente aparte** en `components/dialog/<...>/steps/`. El padre (`Dialog`) solo:

- Decide qué step renderizar (vía el `step` del hook).
- Renderiza el header (título, descripción, ícono).
- Pasa los callbacks del hook a cada step.

```tsx
// components/dialog/twoFactorDisabled/steps/VerifyOtpStep.tsx
interface VerifyOtpStepProps {
  onBack: () => void;
  onSuccess: () => void;
}

function VerifyOtpStep({ onBack, onSuccess }: VerifyOtpStepProps) {
  // ...
}

export default VerifyOtpStep;
```

**Convención:** cada step recibe **solo lo que necesita** (callbacks + estado), nada más. La lógica de transición vive en el hook.

### 7.2. Componentes default export, helpers named export

- **Componentes** se exportan con `export default function Name()`.
- **Helpers internos** (íconos, headers, wrappers) se quedan en el mismo archivo pero como funciones aparte sin `export`.

```tsx
function DialogHeaderIcon({ step }: { step: TwoFactorActivationStep }) { ... }
function DialogHeaderIconWrapper({ icon, ... }) { ... }

export default function TwoFactorSetupDialog() { ... }
```

### 7.3. Usá shadcn/ui como base

Para cualquier UI nueva:

- Botones, inputs, dialogs, alerts, dropdowns → `shared/components/shadcn/ui/`.
- Wrappers o composiciones → en `shared/components/` (genérico) o en el módulo (específico).
- Animaciones / efectos → `shared/components/reactBits/`.

**No inventes** estilos custom para cosas que shadcn ya resuelve.

## 8. Convenciones de formularios

### 8.1. Form de Inertia + Wayfinder

Para enviar datos al backend, usá `<Form>` de `@inertiajs/react` con la action generada por **Wayfinder**:

```tsx
import { Form } from "@inertiajs/react";
import { confirm } from "@/shared/wayfinder/routes/two-factor";

<Form {...confirm.form()} onSuccess={onSuccess} resetOnError resetOnSuccess>
  {({ errors, processing }) => (
    // ...
  )}
</Form>
```

- `confirm.form()` devuelve los props que el form necesita (action + method + token CSRF).
- `resetOnError` y `resetOnSuccess` resetean inputs nativos. Si tenés un componente controlado (ej. `InputOTP`), pasale `value={...}` y limpia el state en `onError` para que sincronice.

### 8.2. Inputs OTP con `input-otp`

Para inputs de tipo OTP / PIN, usá el wrapper de shadcn `@/shared/components/shadcn/ui/input-otp` con `REGEXP_ONLY_DIGITS`:

```tsx
import { REGEXP_ONLY_DIGITS } from "input-otp";

import { InputOTP, InputOTPGroup, InputOTPSlot } from "@/shared/components/shadcn/ui/input-otp";

const [code, setCode] = useState<string>("");

<InputOTP
  id="otp"
  name="code"
  value={code} // ← controlado: sincroniza state interno
  onChange={setCode}
  maxLength={OTP_MAX_LENGTH}
  pattern={REGEXP_ONLY_DIGITS}
>
  <InputOTPGroup>
    {Array.from({ length: OTP_MAX_LENGTH }, (_, i) => (
      <InputOTPSlot index={i} key={i} />
    ))}
  </InputOTPGroup>
</InputOTP>;
```

**Importante:** la lib `input-otp` mantiene state interno. Si solo pasás `onChange` y querés resetear el input (por ejemplo tras un error), **tenés que pasar también `value={code}`** y limpiar el state en `onError`:

```tsx
<Form
  {...confirm.form()}
  onError={() => setCode("")}      // ← limpia el state → InputOTP se sincroniza
  resetOnError
  resetOnSuccess
>
```

### 8.3. Errores de validación

- Errores **por campo** → `InputError message={errors?.campo}`.
- Errores **globales** (del setup, fetch fallido, etc.) → `AlertError errors={errors}`.

```tsx
{errors?.length ? <AlertError errors={errors} /> : ...}
<InputError message={errors?.confirmTwoFactorAuthentication?.code} />
```

## 9. Convenciones de estilos

### 9.1. Tailwind v4 utility-first

Todo el styling va con **Tailwind utility classes**. No crear archivos CSS por componente.

- **Combiná clases** con `cn()` de `@/shared/lib` (que usa `clsx` + `tailwind-merge`):

  ```tsx
  import { cn } from "@/shared/lib";

  <div className={cn("base-class", isActive && "active-class", className)} />;
  ```

- **Colores** usá los tokens semánticos de shadcn: `bg-background`, `text-foreground`, `border-border`, `bg-muted`, `text-muted-foreground`, etc.
- **Iconos** siempre desde `lucide-react` (per `components.json` → `iconLibrary: "lucide"`).

### 9.2. Variantes de shadcn

Para variantes de Button, Alert, Badge, etc., usá las variantes predefinidas de shadcn:

```tsx
<Button variant="outline" size="icon-sm" className="...">
  <ArrowLeft />
</Button>

<Alert variant="destructive"> ... </Alert>
```

## 10. Convenciones de Inertia + Wayfinder

- **No hardcodees URLs.** Usá siempre las funciones de Wayfinder generadas:
  - `@/shared/wayfinder/routes/...` para rutas nombradas.
  - `@/shared/wayfinder/actions/...` para controllers.
- **No edites** nada en `shared/wayfinder/`. Se regenera con el plugin de Vite.
- Para hacer `POST/PUT/DELETE`, usá los helpers `.form()` (auto-incluye CSRF) o `useHttp()` (peticiones standalone v3).

## 11. TypeScript estricto

- `npm run types` debe pasar limpio. Configurado en `tsconfig.json` con `strict: true`.
- **Ningún `any` ni `unknown`** en el código de feature. Si no podés tipar algo, usá `unknown` solo en el `catch` de un `try/catch` y refinalo.
- **Tipos de retorno explícitos** en funciones declaradas (no flechas asignadas).
- **Interfaces con `;` final** (consistente con la config de Prettier del proyecto).

## 12. Cómo crear un módulo nuevo

Checklist en orden:

1. **Crear la estructura de carpetas:**

   ```bash
   mkdir -p resources/js/modules/<domain>/{components,hooks,types,data}
   ```

2. **Definir los tipos** en `types/` (y enums con const-as-keys si hace falta en `enums/`).

3. **Crear los hooks** en `hooks/` (uno de feature, otro de visibilidad si hay modal).

4. **Crear los componentes** de UI en `components/`. Si es un modal con varios pasos, hacé sub-componentes en `components/dialog/.../steps/`.

5. **Crear el barrel export** en `index.ts`.

6. **Consumir desde la página o layout** vía `@/modules/<domain>`.

7. **Validar:**
   ```bash
   npm run types
   npm run lint:check
   npm run format:check
   ```

## 13. Ejemplo canónico: módulo `setting/modules/twoFactor`

```
resources/js/modules/setting/modules/twoFactor/
├── components/
│   ├── dialog/
│   │   ├── twoFactorDisabled/
│   │   │   ├── TwoFactorSetupDialog.tsx       ← shell del modal
│   │   │   └── steps/                        ← un archivo por paso
│   │   │       ├── ChooseMethodStep.tsx
│   │   │       ├── ManualSetupStep.tsx
│   │   │       ├── VerifyOtpStep.tsx
│   │   │       └── TwoFactorSuccessStep.tsx
│   │   ├── twoFactorEnable/
│   │   │   ├── DisabledTwoFactorDialog.tsx
│   │   │   └── RegenerateCodesDialog.tsx
│   └── ui/                                   ← SummaryCard, OptionCard, Timeline, etc.
├── data/
│   ├── twoFactorDisabled.ts                  ← arrays de UI (benefits, steps, …)
│   └── twoFactorEnable.ts
├── hooks/
│   ├── useTwoFactorAuth.ts                   ← estado + fetch del feature
│   ├── useTwoFactorActivationFlow.ts         ← state machine del wizard
│   └── useTwoFactorActivationFlow.ts         ← state machine del wizard
├── types/
│   └── twoFactorActivationStep.ts            ← const-as-keys + tipo derivado
├── utils/
│   ├── colorHoverMap.ts
│   └── downloadRecoveryCodes.ts
└── views/
    ├── TwoFactorDisabled.tsx                 ← vista principal (deshabilitado)
    └── TwoFactorEnable.tsx                   ← vista cuando ya está activo
```

**Patrón que implementa:**

- `view` (TwoFactorDisabled) usa `useTwoFactorAuth` (feature) + `useDialog` (visibilidad del modal de setup) y pasa props al `TwoFactorSetupDialog`.
- `TwoFactorSetupDialog` es solo el shell — la lógica vive en `useTwoFactorActivationFlow`.
- Cada paso del wizard es un componente aparte en `steps/`, con props mínimas.
- El tipo de paso es const-as-keys (`twoFactorActivationStepKey` + `TwoFactorActivationStep`).
- Los íconos del header viven en el dialog padre; el contenido vive en los steps.

Usa este módulo como referencia para wizards / modales con varios pasos.
