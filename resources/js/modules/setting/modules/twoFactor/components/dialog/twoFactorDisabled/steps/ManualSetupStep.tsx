import type { JSX } from "react";

import { FaCheckCircle } from "react-icons/fa";

import type { LucideIcon } from "lucide-react";
import { AlertTriangleIcon, Check, Copy, Info, Loader2, RotateCcw } from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from "@/shared/components/shadcn/ui/input-group";
import { Progress } from "@/shared/components/shadcn/ui/progress";

import { cn } from "@/shared/lib";
import { alertVariants } from "@/shared/lib/styling";

import { useCopyWithCountdown } from "../../../../hooks/useCopyWithCountdown";

interface ManualSetupStepProps {
  errors: string[];
  manualSetupKey: string | null;
  onContinue: () => void;
  onRetry: () => void;
}

const COPY_FEEDBACK_DURATION_MS = 10_000;
const COPY_FEEDBACK_TICK_MS = 1_000;

function ManualSetupStep({
  errors,
  manualSetupKey,
  onContinue,
  onRetry,
}: ManualSetupStepProps): JSX.Element {
  const { copiedText, copy, isActive, progressPercent, secondsLeft } = useCopyWithCountdown({
    durationMs: COPY_FEEDBACK_DURATION_MS,
    tickMs: COPY_FEEDBACK_TICK_MS,
  });

  const hasError = errors.length > 0;
  const isCopied = copiedText !== null && copiedText === manualSetupKey;

  const handleCopy = (): void => {
    if (!manualSetupKey) {
      return;
    }

    copy(manualSetupKey);
  };

  if (hasError) {
    return <StepErrorAlert onRetry={onRetry} />;
  }

  return (
    <div className="flex w-full flex-col items-center space-y-5">
      <div className="flex w-full flex-col space-y-2">
        <label htmlFor="manual-setup-key" className="text-sm font-medium text-muted-foreground">
          Código manual
        </label>

        <ManualSetupKeyInput
          Icon={isCopied ? Check : Copy}
          manualSetupKey={manualSetupKey}
          onCopy={handleCopy}
        />

        {isActive && (
          <CopyFeedbackAlert progressPercent={progressPercent} secondsLeft={secondsLeft} />
        )}
      </div>

      <Alert className={alertVariants.info}>
        <Info />
        <AlertTitle>Consejo</AlertTitle>
        <AlertDescription>
          En tu aplicación, selecciona &quot;Ingresar clave manualmente&quot; o &quot;Agregar cuenta
          manualmente&quot; y pega esta clave.
        </AlertDescription>
      </Alert>

      <div className="flex w-full space-x-5">
        <Button className="w-full cursor-pointer" onClick={onContinue}>
          Entendido
        </Button>
      </div>
    </div>
  );
}

type StepErrorAlertProps = Pick<ManualSetupStepProps, "onRetry">;

function StepErrorAlert({ onRetry }: StepErrorAlertProps) {
  return (
    <div className="flex w-full flex-col items-center space-y-2">
      <Alert className={cn(alertVariants.destructive, "my-2")}>
        <AlertTriangleIcon />
        <AlertTitle>Algo salió mal</AlertTitle>
        <AlertDescription>No pudimos cargar la información. Inténtalo de nuevo.</AlertDescription>
      </Alert>

      <Button className="w-full cursor-pointer" onClick={onRetry} type="button" variant="outline">
        <RotateCcw />
        Reintentar
      </Button>
    </div>
  );
}

type ManualSetupKeyInputProps = Pick<ManualSetupStepProps, "manualSetupKey"> & {
  Icon: LucideIcon;
  onCopy: () => void;
};

function ManualSetupKeyInput({ Icon, manualSetupKey, onCopy }: ManualSetupKeyInputProps) {
  if (!manualSetupKey) {
    return (
      <div
        aria-busy
        className="flex h-9 w-full items-center justify-center rounded-md border border-input bg-muted/30"
      >
        <Loader2 className="size-4 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <InputGroup>
      <InputGroupInput
        className="font-mono"
        id="manual-setup-key"
        readOnly
        value={manualSetupKey}
      />

      <InputGroupAddon align="inline-end">
        <InputGroupButton
          aria-label="Copiar código manual"
          onClick={onCopy}
          size="icon-sm"
          className="cursor-pointer"
        >
          <Icon className="size-4" />
        </InputGroupButton>
      </InputGroupAddon>
    </InputGroup>
  );
}

interface CopyFeedbackAlertProps {
  progressPercent: number;
  secondsLeft: number;
}

function CopyFeedbackAlert({ progressPercent, secondsLeft }: CopyFeedbackAlertProps) {
  return (
    <Alert
      role="status"
      aria-live="polite"
      className={cn(alertVariants.success, "text-green-900 dark:text-green-400")}
    >
      <FaCheckCircle />

      <AlertTitle>Clave copiada al portapapeles</AlertTitle>

      <AlertDescription>
        <span>
          Este mensaje desaparecerá automáticamente en {secondsLeft}{" "}
          {secondsLeft === 1 ? "segundo" : "segundos"}.
        </span>

        <div className="mt-2 flex w-full items-center gap-3">
          <Progress
            value={progressPercent}
            aria-label="Tiempo restante"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={Math.round(progressPercent)}
            className={cn(
              "h-1 bg-green-200 dark:bg-green-800",
              "**:data-[slot=progress-indicator]:bg-green-700 dark:**:data-[slot=progress-indicator]:bg-green-400",
              "**:data-[slot=progress-indicator]:duration-1000!",
              "**:data-[slot=progress-indicator]:ease-linear!",
            )}
          />

          <span
            aria-live="off"
            className="text-xs font-semibold text-green-900 dark:text-green-400"
          >
            {secondsLeft}s
          </span>
        </div>
      </AlertDescription>
    </Alert>
  );
}

export default ManualSetupStep;
