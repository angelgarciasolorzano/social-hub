import { useCallback, useEffect } from "react";

import { ArrowDown, CircleCheck, Info } from "lucide-react";

import AlertError from "@/shared/components/AlertError";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";

import { downloadRecoveryCodes } from "../../../../utils/downloadRecoveryCodes";

interface TwoFactorSuccessStepProps {
  errors: string[];
  fetchRecoveryCodes: () => Promise<void>;
  onClose: () => void;
  recoveryCodesList: string[];
}

function TwoFactorSuccessStep({
  errors,
  fetchRecoveryCodes,
  onClose,
  recoveryCodesList,
}: TwoFactorSuccessStepProps) {
  useEffect(() => {
    if (!recoveryCodesList.length) {
      void fetchRecoveryCodes();
    }
  }, [recoveryCodesList.length, fetchRecoveryCodes]);

  const handleDownload = useCallback((): void => {
    downloadRecoveryCodes(recoveryCodesList);
  }, [recoveryCodesList]);

  return (
    <>
      {errors?.length ? (
        <AlertError errors={errors} title="No se pudieron cargar los códigos de respaldo." />
      ) : (
        <>
          <div className="mb-3 rounded-full border border-border bg-card p-0.5 shadow-sm">
            <div className="rounded-full border border-border bg-green-50 p-2.5 dark:bg-green-500/10">
              <CircleCheck className="size-6 text-green-600 dark:text-green-400" />
            </div>
          </div>

          <div className="flex w-full flex-col items-center space-y-1 text-center">
            <h3 className="text-base font-semibold text-foreground">
              ¡2FA activado correctamente!
            </h3>

            <p className="text-sm text-muted-foreground">
              Guarda estos códigos de respaldo en un lugar seguro.
              <br />
              Te permitirán acceder a tu cuenta si pierdes tu dispositivo.
            </p>
          </div>

          <div className="grid w-full grid-cols-2 gap-2">
            {recoveryCodesList.length
              ? recoveryCodesList.map((code) => (
                  <div
                    key={code}
                    className="rounded-md border border-border bg-muted/40 px-3 py-2 text-center font-mono text-sm text-foreground"
                  >
                    {code}
                  </div>
                ))
              : Array.from({ length: 8 }, (_, index) => (
                  <div
                    key={`skeleton-${index}`}
                    className="h-9 animate-pulse rounded-md border border-border bg-muted/40"
                  />
                ))}
          </div>

          <Alert className="w-full border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400">
            <Info />
            <AlertTitle>Importante</AlertTitle>
            <AlertDescription>
              Cada código solo se puede usar una vez. Guarda o descarga estos códigos ahora.
            </AlertDescription>
          </Alert>

          <div className="flex w-full space-x-5">
            <Button
              type="button"
              variant="outline"
              className="flex-1 cursor-pointer"
              onClick={handleDownload}
              disabled={!recoveryCodesList.length}
            >
              <ArrowDown />
              Descargar .txt
            </Button>

            <Button type="button" className="flex-1 cursor-pointer" onClick={onClose}>
              Entendido
            </Button>
          </div>
        </>
      )}
    </>
  );
}

export default TwoFactorSuccessStep;
