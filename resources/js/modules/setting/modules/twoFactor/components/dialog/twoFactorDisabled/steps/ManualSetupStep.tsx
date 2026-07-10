import { useEffect, useState } from "react";

import { FaCheckCircle } from "react-icons/fa";

import { Check, Copy, Info, Loader2 } from "lucide-react";

import AlertError from "@/shared/components/AlertError";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Progress } from "@/shared/components/shadcn/ui/progress";

import { useClipboard } from "@/shared/hooks/useClipboard";

interface ManualSetupStepProps {
  errors: string[];
  manualSetupKey: string | null;
  onContinue: () => void;
}

const COPY_FEEDBACK_DURATION_MS = 10_000;
const COPY_FEEDBACK_TICK_MS = 1_000;
const COPY_FEEDBACK_TOTAL_SECONDS = COPY_FEEDBACK_DURATION_MS / COPY_FEEDBACK_TICK_MS;

function ManualSetupStep({ errors, manualSetupKey, onContinue }: ManualSetupStepProps) {
  const [copiedText, copy] = useClipboard({ resetTimeout: COPY_FEEDBACK_DURATION_MS });

  const [copyStartedAt, setCopyStartedAt] = useState<number | null>(null);
  const [secondsLeft, setSecondsLeft] = useState<number>(0);

  const IconComponent = copiedText === manualSetupKey ? Check : Copy;
  const progressValue = (secondsLeft / COPY_FEEDBACK_TOTAL_SECONDS) * 100;

  useEffect(() => {
    if (copyStartedAt === null) {
      return;
    }

    const interval = setInterval(() => {
      setSecondsLeft((prev) => Math.max(0, prev - 1));
    }, COPY_FEEDBACK_TICK_MS);

    const timeout = setTimeout(() => {
      setCopyStartedAt(null);
    }, COPY_FEEDBACK_DURATION_MS);

    return () => {
      clearInterval(interval);
      clearTimeout(timeout);
    };
  }, [copyStartedAt]);

  const handleCopy = (): void => {
    if (!manualSetupKey) {
      return;
    }

    void copy(manualSetupKey);
    setSecondsLeft(COPY_FEEDBACK_TOTAL_SECONDS);
    setCopyStartedAt(Date.now());
  };

  return (
    <div className="flex w-full flex-col items-center space-y-5">
      {errors?.length ? (
        <AlertError errors={errors} />
      ) : (
        <>
          <div className="flex w-full flex-col space-y-2">
            <label htmlFor="manual-setup-key" className="text-sm font-medium text-muted-foreground">
              Código manual
            </label>

            <div className="flex w-full items-stretch overflow-hidden rounded-xl border border-border">
              {!manualSetupKey ? (
                <div className="flex h-full w-full items-center justify-center bg-muted p-3">
                  <Loader2 className="size-4 animate-spin" />
                </div>
              ) : (
                <>
                  <input
                    id="manual-setup-key"
                    type="text"
                    className="h-full w-full bg-background p-3 font-mono text-sm text-foreground outline-none"
                    readOnly
                    value={manualSetupKey}
                  />

                  <button
                    type="button"
                    aria-label="Copiar código manual"
                    className="cursor-pointer border-l border-border px-3 hover:bg-muted"
                    onClick={handleCopy}
                  >
                    <IconComponent className="w-4" />
                  </button>
                </>
              )}
            </div>

            {copyStartedAt !== null && (
              <Alert
                role="status"
                aria-live="polite"
                className="border-green-200 bg-green-50 text-green-900 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-500"
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
                      value={progressValue}
                      aria-label="Tiempo restante"
                      aria-valuemin={0}
                      aria-valuemax={100}
                      aria-valuenow={Math.round(progressValue)}
                      className="h-1 bg-green-200 **:data-[slot=progress-indicator]:bg-green-700 **:data-[slot=progress-indicator]:duration-1000! **:data-[slot=progress-indicator]:ease-linear!"
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
            )}
          </div>

          <Alert className="border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-500">
            <Info />
            <AlertTitle>Consejo</AlertTitle>
            <AlertDescription>
              En tu aplicación, selecciona &quot;Ingresar clave manualmente&quot; o &quot;Agregar
              cuenta manualmente&quot; y pega esta clave.
            </AlertDescription>
          </Alert>

          <div className="flex w-full space-x-5">
            <Button className="w-full cursor-pointer" onClick={onContinue}>
              Entendido
            </Button>
          </div>
        </>
      )}
    </div>
  );
}

export default ManualSetupStep;
