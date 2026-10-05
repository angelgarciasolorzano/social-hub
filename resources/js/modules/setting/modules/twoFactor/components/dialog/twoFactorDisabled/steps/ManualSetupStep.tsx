import type { JSX } from "react";
import { useId } from "react";

import { FaCheckCircle } from "react-icons/fa";

import type { LucideIcon } from "lucide-react";
import { AlertTriangleIcon, Check, Copy, Info, Loader2, RotateCcw } from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Field, FieldDescription, FieldLabel } from "@/shared/components/shadcn/ui/field";
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
  const manualSetupKeyId = useId();
  const manualSetupInstructionsId = useId();
  const manualSetupLoadingId = useId();

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
      <Field className="w-full">
        <FieldLabel htmlFor={manualSetupKeyId}>Código manual</FieldLabel>

        <FieldDescription className="flex items-start gap-2" id={manualSetupInstructionsId}>
          <Info aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
          <span>
            En tu aplicación, selecciona &quot;Ingresar clave manualmente&quot; o &quot;Agregar
            cuenta manualmente&quot; y pega esta clave.
          </span>
        </FieldDescription>

        <ManualSetupKeyInput
          Icon={isCopied ? Check : Copy}
          id={manualSetupKeyId}
          instructionsId={manualSetupInstructionsId}
          loadingId={manualSetupLoadingId}
          manualSetupKey={manualSetupKey}
          onCopy={handleCopy}
        />

        {isActive && (
          <CopyFeedbackAlert progressPercent={progressPercent} secondsLeft={secondsLeft} />
        )}
      </Field>

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
        <AlertTriangleIcon aria-hidden="true" />
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
  id: string;
  instructionsId: string;
  loadingId: string;
  onCopy: () => void;
};

function ManualSetupKeyInput({
  Icon,
  id,
  instructionsId,
  loadingId,
  manualSetupKey,
  onCopy,
}: ManualSetupKeyInputProps) {
  const isLoading = !manualSetupKey;
  const describedBy = isLoading ? `${instructionsId} ${loadingId}` : instructionsId;

  return (
    <>
      <InputGroup aria-busy={isLoading}>
        <InputGroupInput
          aria-describedby={describedBy}
          className="font-mono"
          disabled={isLoading}
          id={id}
          readOnly
          value={manualSetupKey ?? ""}
        />

        <InputGroupAddon align="inline-end">
          {isLoading ? (
            <Loader2 aria-hidden="true" className="size-4 animate-spin text-muted-foreground" />
          ) : (
            <InputGroupButton
              aria-label="Copiar código manual"
              onClick={onCopy}
              size="icon-sm"
              className="cursor-pointer"
            >
              <Icon aria-hidden="true" className="size-4" />
            </InputGroupButton>
          )}
        </InputGroupAddon>
      </InputGroup>

      {isLoading && (
        <span className="sr-only" id={loadingId} role="status">
          Cargando código manual.
        </span>
      )}
    </>
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
      <FaCheckCircle aria-hidden="true" />

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
