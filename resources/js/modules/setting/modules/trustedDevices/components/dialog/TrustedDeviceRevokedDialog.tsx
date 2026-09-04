import type { JSX } from "react";

import { router } from "@inertiajs/react";

import { ArrowRight, CircleAlert, ShieldOff } from "lucide-react";

import {
  TrustedDeviceDetailsHeader,
  TrustedDeviceInfoCard,
} from "@/modules/setting/modules/trustedDevices/components/ui/TrustedDeviceInfoCard";
import { trustedDeviceStatusFilterValue } from "@/modules/setting/modules/trustedDevices/data/trustedDeviceFilters";
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

interface TrustedDeviceRevokedDialogProps {
  existingDevice: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

function TrustedDeviceRevokedDialog({
  existingDevice,
  open,
  onClose,
}: TrustedDeviceRevokedDialogProps): JSX.Element {
  const handleGoToRevokedList = (): void => {
    onClose();
    router.visit(index({ query: { status: trustedDeviceStatusFilterValue.revoked } }).url, {
      preserveScroll: true,
      preserveState: true,
    });
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
              <ShieldOff className="h-5 w-5 text-muted-foreground" />
              Dispositivo revocado
            </div>
          </DialogTitle>
          <DialogDescription>
            Este dispositivo ya fue registrado pero lo revocaste. Para volver a confiar en el,
            reactivarlo desde la lista de dispositivos revocados.
          </DialogDescription>
        </DialogHeader>

        <Separator />

        <TrustedDeviceDetailsHeader
          description="Asi es como identificamos este dispositivo antes de ser revocado."
          title="Detalles del dispositivo revocado"
        />

        <TrustedDeviceInfoCard device={existingDevice} expirationLabel="Expira el" />

        <RevokedActionsAlert onGoToRevokedList={handleGoToRevokedList} />

        <DialogFooter>
          <Button onClick={onClose} type="button" variant="outline">
            Cerrar
          </Button>
          <Button onClick={handleGoToRevokedList} type="button">
            Ir a dispositivos revocados
            <ArrowRight />
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface RevokedActionsAlertProps {
  onGoToRevokedList: () => void;
}

function RevokedActionsAlert({ onGoToRevokedList }: RevokedActionsAlertProps): JSX.Element {
  return (
    <Alert className={alertVariants.warning}>
      <CircleAlert />
      <AlertTitle>¿Quieres volver a confiar en este dispositivo?</AlertTitle>
      <AlertDescription className="flex items-center gap-4">
        Reactivarlo desde la lista de revocados restaura la fila con un token nuevo por seguridad.
        <Button onClick={onGoToRevokedList} size="sm" variant="outline">
          Ir a la lista de revocados
          <ArrowRight />
        </Button>
      </AlertDescription>
    </Alert>
  );
}

export default TrustedDeviceRevokedDialog;
