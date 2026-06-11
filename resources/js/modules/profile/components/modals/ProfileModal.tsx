import type { Dispatch, SetStateAction } from "react";

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/ui/dialog";

interface ProfileModalProps {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

function ProfileModal({ open, setOpen }: ProfileModalProps) {
  return (
    <Dialog onOpenChange={setOpen} open={open}>
      <DialogContent className="mx-w-[80%] flex h-[80%] min-w-[65%] flex-col overflow-hidden">
        <DialogHeader>
          <DialogTitle>Configuracion</DialogTitle>

          <DialogDescription>Aquí puedes configurar tu perfil y preferencias.</DialogDescription>
        </DialogHeader>

        <div>{/* Contenido de configuración va aquí */}</div>
      </DialogContent>
    </Dialog>
  );
}

export default ProfileModal;
