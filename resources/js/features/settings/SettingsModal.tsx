import { Dispatch, SetStateAction, useState } from "react";

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Separator } from "@/components/ui/separator";

import SettingSidebar from "./components/SettingSidebar";
import { SettingLabelSidebar } from "./data/settingsSidebarItems";
import Appearance from "./views/Appearance";

interface SettingsModalProps {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

function SettingsModal({ open, setOpen }: SettingsModalProps) {
  const [active, setActive] = useState<SettingLabelSidebar>("Perfil");

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogContent className="mx-w-[80%] flex h-[80%] min-w-[65%] flex-col overflow-hidden">
        <DialogHeader>
          <DialogTitle>Configuracion</DialogTitle>

          <DialogDescription>Aquí puedes configurar tu perfil y preferencias.</DialogDescription>
        </DialogHeader>

        <Separator />

        <div className="flex h-full">
          <SettingSidebar active={active} setActive={setActive} />

          <main>{active === "Apariencia" && <Appearance />}</main>
        </div>
      </DialogContent>
    </Dialog>
  );
}

export default SettingsModal;
