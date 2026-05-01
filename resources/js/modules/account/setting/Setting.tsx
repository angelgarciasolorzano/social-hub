import type { Dispatch, SetStateAction } from "react";
import { useState } from "react";

import { MdOutlineSettings } from "react-icons/md";

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/ui/dialog";
import { Separator } from "@/shared/components/ui/separator";

import PasswordProtectedView from "./components/password/PasswordProtectedView";
import SettingSidebar from "./components/SettingSidebar";
import SettingViewFooter from "./components/SettingViewFooter";
import SettingViewHeader from "./components/SettingViewHeader";
import { SettingLabelSidebar } from "./data/settingSidebarItems";
import SettingView from "./views/SettingView";

interface SettingsModalProps {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

function Settings({ open, setOpen }: SettingsModalProps) {
  const [active, setActive] = useState<SettingLabelSidebar>(SettingLabelSidebar.Profile);

  return (
    <Dialog onOpenChange={setOpen} open={open}>
      <DialogContent className="mx-w-[80%] flex h-[80%] min-w-[65%] flex-col overflow-hidden">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <MdOutlineSettings className="h-5 w-5 text-gray-600 dark:text-gray-400" />
              Configuración
            </div>
          </DialogTitle>

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
      <SettingSidebar active={active} setActive={setActive} />

      <div className="flex flex-1 flex-col gap-2.5 overflow-y-auto px-4">
        <PasswordProtectedView
          active={active}
          description="Debes confirmar tu contraseña para gestionar la autenticación de dos factores."
          requiredFor={SettingLabelSidebar.TwoFactor}
          title="Two Factor Authentication"
        >
          <SettingViewHeader active={active} />

          <SettingView active={active} />

          <SettingViewFooter active={active} />
        </PasswordProtectedView>
      </div>
    </div>
  );
}

export default Settings;
