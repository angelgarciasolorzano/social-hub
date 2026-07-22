import type { JSX } from "react";

import { AlertTriangleIcon, Info, Loader2, RotateCcw } from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";

interface ChooseMethodStepProps {
  errors: string[];
  qrCodeSvg: string | null;
  onContinue: () => void;
  onOpenManualSetup: () => void;
  onRetry: () => void;
}

function ChooseMethodStep(props: ChooseMethodStepProps): JSX.Element {
  const { errors, qrCodeSvg, onContinue, onOpenManualSetup, onRetry } = props;

  const hasError = errors.length > 0;

  if (hasError) {
    return <StepErrorAlert onRetry={onRetry} />;
  }

  return (
    <div className="flex w-full flex-col items-center space-y-5">
      <div className="mx-auto flex max-w-md overflow-hidden">
        <div className="mx-auto aspect-square w-64 rounded-lg border border-border">
          <div className="z-10 flex h-full w-full items-center justify-center p-5">
            {qrCodeSvg ? (
              <div
                dangerouslySetInnerHTML={{
                  __html: qrCodeSvg,
                }}
              />
            ) : (
              <Loader2 className="flex size-4 animate-spin" />
            )}
          </div>
        </div>
      </div>

      <button
        type="button"
        className="cursor-pointer text-sm font-medium text-violet-700 underline-offset-4 hover:underline dark:text-violet-400"
        onClick={onOpenManualSetup}
      >
        ¿No puedes escanear el código?
      </button>

      <Alert className="border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-500">
        <Info />
        <AlertTitle>Consejo</AlertTitle>
        <AlertDescription>
          Abre tu aplicación y usa la opción para agregar una nueva cuenta.
        </AlertDescription>
      </Alert>

      <div className="flex w-full space-x-5">
        <Button className="w-full cursor-pointer" onClick={onContinue}>
          Continuar
        </Button>
      </div>
    </div>
  );
}

type StepErrorAlertProps = Pick<ChooseMethodStepProps, "onRetry">;

function StepErrorAlert({ onRetry }: StepErrorAlertProps) {
  return (
    <div className="flex w-full flex-col items-center space-y-2">
      <Alert className="my-2 border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-500">
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

export default ChooseMethodStep;
