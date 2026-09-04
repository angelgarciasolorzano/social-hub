import type { JSX } from "react";

import { router } from "@inertiajs/react";

import { ArrowRight, CircleAlert, ShieldAlert } from "lucide-react";

import {
  TrustedDeviceDetailsHeader,
  TrustedDeviceInfoCard,
} from "@/modules/setting/modules/trustedDevices/components/ui/TrustedDeviceInfoCard";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

import { index } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { alertVariants } from "@/shared/lib/styling";

interface TrustedDeviceExpiredDialogProps {
  existingDevice: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

function TrustedDeviceExpiredDialog({
  existingDevice,
  open,
  onClose,
}: TrustedDeviceExpiredDialogProps): JSX.Element {
  const handleGoToExpiredList = (): void => {
    onClose();
    router.visit(index({ query: { status: "inactive" } }).url);
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) {
          onClose();
        }
      }}
    >
      <DialogContent className="max-w-4xl min-w-3xl">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <ShieldAlert className="h-5 w-5 text-muted-foreground" />
              Confianza expirada
            </div>
          </DialogTitle>
          <DialogDescription>
            Este dispositivo ya fue registrado pero su confianza expiro. Para volver a confiar en
            el, renueva su confianza desde la lista de dispositivos expirados.
          </DialogDescription>
        </DialogHeader>

        <Separator />

        <TrustedDeviceDetailsHeader
          description="Asi es como identificamos este dispositivo antes de expirar."
          title="Detalles del dispositivo expirado"
        />

        <TrustedDeviceInfoCard device={existingDevice} expirationLabel="Expiro el" />

        <ExpiredActionsAlert onGoToExpiredList={handleGoToExpiredList} />

        <DialogFooter>
          <Button onClick={onClose} type="button" variant="outline">
            Cerrar
          </Button>
          <Button onClick={handleGoToExpiredList} type="button">
            Ir a dispositivos expirados
            <ArrowRight />
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface ExpiredActionsAlertProps {
  onGoToExpiredList: () => void;
}

function ExpiredActionsAlert({ onGoToExpiredList }: ExpiredActionsAlertProps): JSX.Element {
  return (
    <Alert className={alertVariants.warning}>
      <CircleAlert />
      <AlertTitle>¿Quieres volver a confiar en este dispositivo?</AlertTitle>
      <AlertDescription className="flex items-center gap-4">
        Renueva la confianza para extender la fecha de expiracion sin volver a registrarlo.
        <Button onClick={onGoToExpiredList} size="sm" variant="outline">
          Ir a la lista de expirados
          <ArrowRight />
        </Button>
      </AlertDescription>
    </Alert>
  );
}

export default TrustedDeviceExpiredDialog;
