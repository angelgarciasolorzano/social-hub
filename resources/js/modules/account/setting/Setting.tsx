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
import { passwordProtectedViewMeta } from "./data/passwordProtectedViewMeta";
import type { SettingLabelSidebarValue } from "./data/settingSidebarItems";
import { SettingLabelSidebar } from "./data/settingSidebarItems";
import SettingView from "./views/SettingView";

interface SettingsModalProps {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

function Setting({ open, setOpen }: SettingsModalProps) {
  const [active, setActive] = useState<SettingLabelSidebarValue>(SettingLabelSidebar.Profile);

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
  active: SettingLabelSidebarValue;
  setActive: Dispatch<SetStateAction<SettingLabelSidebarValue>>;
}

function SettingsBody({ active, setActive }: SettingsBodyProps) {
  const meta = passwordProtectedViewMeta[active];

  return (
    <div className="flex h-full min-h-0">
      <SettingSidebar active={active} setActive={setActive} />

      <div className="flex flex-1 flex-col gap-2.5 overflow-y-auto px-4">
        <PasswordProtectedView
          active={active}
          description={meta?.description}
          icon={meta?.icon}
          requiredFor={meta?.requiredFor}
          title={meta?.title}
        >
          <SettingViewHeader active={active} />

          <SettingView active={active} />

          <SettingViewFooter active={active} />
        </PasswordProtectedView>
      </div>
    </div>
  );
}

export default Setting;
