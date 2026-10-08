import type { JSX } from "react";

import { AlertTriangleIcon, RotateCcw } from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";

import { cn } from "@/shared/lib";
import { alertVariants } from "@/shared/lib/styling";

interface TwoFactorStepErrorAlertProps {
  onRetry: () => void;
}

function TwoFactorStepErrorAlert({ onRetry }: TwoFactorStepErrorAlertProps): JSX.Element {
  return (
    <div className="flex w-full flex-col items-center space-y-2">
      <Alert className={cn(alertVariants.destructive, "my-2")}>
        <AlertTriangleIcon aria-hidden="true" />
        <AlertTitle>Algo salió mal</AlertTitle>
        <AlertDescription>No pudimos cargar la información. Inténtalo de nuevo.</AlertDescription>
      </Alert>

      <Button className="w-full cursor-pointer" onClick={onRetry} type="button" variant="outline">
        <RotateCcw aria-hidden="true" />
        Reintentar
      </Button>
    </div>
  );
}

export default TwoFactorStepErrorAlert;
