import type { JSX } from "react";

import { Info, Loader2 } from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";

import { alertVariants } from "@/shared/lib/styling";

import TwoFactorStepErrorAlert from "./TwoFactorStepErrorAlert";

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
    return <TwoFactorStepErrorAlert onRetry={onRetry} />;
  }

  return (
    <div className="flex w-full flex-col items-center space-y-5">
      <div className="mx-auto flex max-w-md overflow-hidden">
        <div className="mx-auto aspect-square w-64 rounded-lg border border-border">
          <div className="z-10 flex h-full w-full items-center justify-center p-5">
            {qrCodeSvg ? (
              <>
                <div
                  aria-label="Código QR para configurar la autenticación de dos factores"
                  dangerouslySetInnerHTML={{
                    __html: qrCodeSvg,
                  }}
                  role="img"
                />
                <span className="sr-only" role="status">
                  Código QR listo para escanear.
                </span>
              </>
            ) : (
              <>
                <Loader2 aria-hidden="true" className="flex size-4 animate-spin" />
                <span className="sr-only" role="status">
                  Cargando código QR.
                </span>
              </>
            )}
          </div>
        </div>
      </div>

      <Button
        type="button"
        className="h-auto cursor-pointer p-0 text-sm font-medium text-violet-700 dark:text-violet-400"
        onClick={onOpenManualSetup}
        variant="link"
      >
        ¿No puedes escanear el código?
      </Button>

      <Alert className={alertVariants.info}>
        <Info aria-hidden="true" />
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

export default ChooseMethodStep;
