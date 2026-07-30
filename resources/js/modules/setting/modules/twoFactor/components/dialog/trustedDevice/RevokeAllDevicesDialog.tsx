import type { JSX } from "react";

import { Trash2 } from "lucide-react";

import type { TrustedDevice } from "@/modules/setting/modules/twoFactor/types/trustedDevice";

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";

interface RevokeAllDevicesDialogProps {
  devices: TrustedDevice[];
  open: boolean;
  onClose: () => void;
}

function RevokeAllDevicesDialog({
  devices: _devices,
  open,
  onClose,
}: RevokeAllDevicesDialogProps): JSX.Element {
  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) onClose();
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <Trash2 className="h-5 w-5 text-muted-foreground" />
              Revocar todos los dispositivos
            </div>
          </DialogTitle>
          <DialogDescription>
            Eliminaras todos los dispositivos de confianza vinculados a tu cuenta. La proxima vez
            que inicies sesion en cualquiera de ellos, se te volvera a solicitar el codigo de
            verificacion.
          </DialogDescription>
        </DialogHeader>

        {/* TODO: Design interior — alert "Que pasara", card con lista de devices, password input, terms checkbox, footer con info + botones */}

        <DialogFooter>{/* TODO: Botones Cancelar + Revocar todos */}</DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default RevokeAllDevicesDialog;
