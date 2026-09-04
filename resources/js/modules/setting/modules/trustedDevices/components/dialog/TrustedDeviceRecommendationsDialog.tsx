import type { JSX } from "react";

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
import { iconColorVariants } from "@/shared/lib/styling";

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
          <DialogTitle>Recomendaciones de seguridad</DialogTitle>
          <DialogDescription>
            Sigue estas recomendaciones para mantener tu cuenta segura.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {trustedDeviceRecommendations.map((recommendation) => {
            const Icon = recommendation.icon;

            return (
              <div
                className="flex items-start gap-4 rounded-lg border p-4"
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

        <Alert>
          <AlertTitle>Consejo</AlertTitle>
          <AlertDescription>
            Estas recomendaciones te ayudan a mantener tu cuenta y tus dispositivos de confianza
            seguros.
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
