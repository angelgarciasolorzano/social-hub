import type { JSX } from "react";
import { useCallback, useEffect } from "react";

import { ArrowDown, Info } from "lucide-react";

import AlertError from "@/shared/components/AlertError";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { cn } from "@/shared/lib";
import { alertVariants } from "@/shared/lib/styling";

import { downloadRecoveryCodes } from "../../../../utils/downloadRecoveryCodes";

interface TwoFactorSuccessStepProps {
  errors: string[];
  fetchRecoveryCodes: () => Promise<void>;
  onClose: () => void;
  recoveryCodesList: string[];
}

function TwoFactorSuccessStep(props: TwoFactorSuccessStepProps): JSX.Element {
  const { errors, fetchRecoveryCodes, onClose, recoveryCodesList } = props;

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
      {errors.length ? (
        <AlertError errors={errors} title="No se pudieron cargar los códigos de respaldo." />
      ) : (
        <>
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
                  <Skeleton
                    key={`skeleton-${index}`}
                    aria-label="Cargando códigos de respaldo"
                    className="h-9 border border-border"
                  />
                ))}
          </div>

          <Alert className={cn(alertVariants.warning, "w-full")}>
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
