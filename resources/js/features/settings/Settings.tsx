import { Dispatch, SetStateAction, useState } from "react";

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Separator } from "@/components/ui/separator";

import SettingsSidebar from "./components/SettingsSidebar";
import SettingsViewFooter from "./components/SettingsViewFooter";
import SettingsViewHeader from "./components/SettingsViewHeader";
import { SettingLabelSidebar } from "./data/settingsSidebarItems";
import SettingsView from "./views/SettingsView";

interface SettingsModalProps {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

function Settings({ open, setOpen }: SettingsModalProps) {
  const [active, setActive] = useState<SettingLabelSidebar>(SettingLabelSidebar.Perfil);

  return (
    <Dialog onOpenChange={setOpen} open={open}>
      <DialogContent className="mx-w-[80%] flex h-[80%] min-w-[65%] flex-col overflow-hidden">
        <DialogHeader>
          <DialogTitle>Configuracion</DialogTitle>

          <DialogDescription>Aquí puedes configurar tu perfil y preferencias.</DialogDescription>
        </DialogHeader>

        <Separator />

        <SettingsBody active={active} setActive={setActive} />
      </DialogContent>
    </Dialog>
  );
}

interface SettingsBodyProps {
  active: SettingLabelSidebar;
  setActive: Dispatch<SetStateAction<SettingLabelSidebar>>;
}

function SettingsBody({ active, setActive }: SettingsBodyProps) {
  return (
    <div className="flex h-full min-h-0">
      <SettingsSidebar active={active} setActive={setActive} />

      <div className="flex flex-1 flex-col gap-2.5 overflow-y-auto px-4">
        <SettingsViewHeader active={active} />

        <SettingsView active={active} />

        <SettingsViewFooter active={active} />
      </div>
    </div>
  );
}

export default Settings;
