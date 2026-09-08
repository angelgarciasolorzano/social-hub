import type { JSX } from "react";

import { CircleAlert, Lightbulb } from "lucide-react";

import { trustedDeviceRecommendations } from "@/modules/setting/modules/trustedDevices/data/trustedDevicesOverview";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";

import { cn } from "@/shared/lib";
import { alertVariants, iconColorVariants } from "@/shared/lib/styling";

interface TrustedDeviceRecommendationsDialogProps {
  open: boolean;
  onClose: () => void;
}

function TrustedDeviceRecommendationsDialog({
  open,
  onClose,
}: TrustedDeviceRecommendationsDialogProps): JSX.Element {
  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <Lightbulb className="h-5 w-5 text-muted-foreground" />
              Recomendaciones de seguridad
            </div>
          </DialogTitle>
          <DialogDescription>
            Sigue estas recomendaciones para mantener tu cuenta y dispositivos de confianza seguros.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {trustedDeviceRecommendations.map((recommendation) => {
            const Icon = recommendation.icon;

            return (
              <div
                className="flex items-start gap-4 rounded-lg border bg-card p-4 shadow-sm dark:bg-input/20"
                key={recommendation.title}
              >
                <div
                  className={cn(
                    "flex h-10 w-10 shrink-0 rounded-full p-2",
                    iconColorVariants[recommendation.iconColor].iconBgClass,
                  )}
                >
                  <Icon
                    className={cn(
                      "h-6 w-6",
                      iconColorVariants[recommendation.iconColor].iconFgClass,
                    )}
                  />
                </div>

                <div className="space-y-1">
                  <h4 className="text-sm font-semibold">{recommendation.title}</h4>
                  <p className="text-sm text-muted-foreground">{recommendation.description}</p>
                </div>
              </div>
            );
          })}
        </div>

        <Alert className={alertVariants.info}>
          <CircleAlert />

          <AlertTitle>Estas recomendaciones te ayudan a mantener tu cuenta segura</AlertTitle>

          <AlertDescription>
            Los dispositivos de confianza te permiten iniciar sesion mas rapido, pero es importante
            revisarlos y mantener solo los que utilizas.
          </AlertDescription>
        </Alert>

        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">Cerrar</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default TrustedDeviceRecommendationsDialog;
