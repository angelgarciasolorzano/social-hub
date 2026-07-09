import { Check, Copy, Info, Loader2 } from "lucide-react";

import AlertError from "@/shared/components/AlertError";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";

import { useClipboard } from "@/shared/hooks/useClipboard";

interface ManualSetupStepProps {
  errors: string[];
  manualSetupKey: string | null;
  onContinue: () => void;
}

function ManualSetupStep({ errors, manualSetupKey, onContinue }: ManualSetupStepProps) {
  const [copiedText, copy] = useClipboard();

  const IconComponent = copiedText === manualSetupKey ? Check : Copy;

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
                    onClick={() => {
                      if (manualSetupKey) {
                        void copy(manualSetupKey);
                      }
                    }}
                  >
                    <IconComponent className="w-4" />
                  </button>
                </>
              )}
            </div>
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
