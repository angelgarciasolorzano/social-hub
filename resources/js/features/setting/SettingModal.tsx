import { Dispatch, SetStateAction } from "react";

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Separator } from "@/components/ui/separator";

import SettingsSidebar from "./components/SettingSidebar";

interface SettingsModalProps {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

function SettingModal({ open, setOpen }: SettingsModalProps) {
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogContent className="mx-w-[80%] flex h-[80%] min-w-[65%] flex-col overflow-hidden">
        <DialogHeader>
          <DialogTitle>Configuracion</DialogTitle>

          <DialogDescription>Aquí puedes configurar tu perfil y preferencias.</DialogDescription>
        </DialogHeader>

        <Separator />

        {/* <div>Contenido de configuración va aquí</div> */}
        <SettingsSidebar />
      </DialogContent>
    </Dialog>
  );
}

export default SettingModal;
