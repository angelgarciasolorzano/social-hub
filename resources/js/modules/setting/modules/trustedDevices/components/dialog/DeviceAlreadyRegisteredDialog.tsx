import type { JSX } from "react";

import { router } from "@inertiajs/react";

import { ArrowRight, CircleAlert, ShieldCheck } from "lucide-react";

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

interface DeviceAlreadyRegisteredDialogProps {
  existingDevice: TrustedDevice;
  open: boolean;
  onClose: () => void;
  showListLink?: boolean;
}

function DeviceAlreadyRegisteredDialog({
  existingDevice,
  open,
  onClose,
  showListLink = true,
}: DeviceAlreadyRegisteredDialogProps): JSX.Element {
  const handleGoToList = (): void => {
    onClose();
    router.visit(index().url);
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
              <ShieldCheck className="h-5 w-5 text-muted-foreground" />
              Dispositivo ya registrado
            </div>
          </DialogTitle>
          <DialogDescription>
            Este dispositivo ya esta registrado como de confianza en tu cuenta. No es necesario
            agregarlo nuevamente.
          </DialogDescription>
        </DialogHeader>

        <Separator />

        <TrustedDeviceDetailsHeader
          description="Asi es como identificamos este dispositivo actualmente."
          title="Detalles del dispositivo registrado"
        />

        <TrustedDeviceInfoCard device={existingDevice} expirationLabel="Expira el" />

        {showListLink && <AlreadyRegisteredActionsAlert onGoToList={handleGoToList} />}

        <DialogFooter>
          <Button onClick={onClose} type="button">
            Cerrar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function AlreadyRegisteredActionsAlert({ onGoToList }: { onGoToList: () => void }): JSX.Element {
  return (
    <Alert className={alertVariants.info}>
      <CircleAlert />
      <AlertTitle>¿Necesitar hacer cambios?</AlertTitle>
      <AlertDescription className="flex items-center gap-4">
        Puedes administrar este dispositivo desde la lista de dispositivos de confianza.
        <Button onClick={onGoToList} size="sm" variant="outline">
          Ir a dispositivos de confianza
          <ArrowRight />
        </Button>
      </AlertDescription>
    </Alert>
  );
}

export default DeviceAlreadyRegisteredDialog;
