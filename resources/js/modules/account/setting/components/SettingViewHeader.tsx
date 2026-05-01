import { usePage } from "@inertiajs/react";

import { Badge } from "@/shared/components/ui/badge";

import { cn } from "@/shared/lib/utils";

import type { SharedData } from "@/shared/types";

import { settingHeaderItems } from "../data/settingHeaderItems";
import type { SettingLabelSidebarValue } from "../data/settingSidebarItems";
import { SettingLabelSidebar } from "../data/settingSidebarItems";
import DeleteUser from "./DeleteUser";

interface SettingViewHeaderProps {
  active: SettingLabelSidebarValue;
}

function SettingsViewHeader({ active }: SettingViewHeaderProps) {
  const Icon = settingHeaderItems[active].icon;

  return (
    <header className="space-y-2 border-b pb-2">
      <div className="flex items-center justify-between">
        <h2 className="flex gap-2 leading-none font-semibold text-primary">
          <Icon />

          {settingHeaderItems[active].label}
        </h2>

        <SettingsViewHeaderAddon active={active} />
      </div>

      <p className="text-xs text-muted-foreground">{settingHeaderItems[active].description}</p>
    </header>
  );
}

type SettingsViewHeaderAddonProps = SettingViewHeaderProps;

function SettingsViewHeaderAddon({ active }: SettingsViewHeaderAddonProps) {
  const { twoFactorEnabled } = usePage<SharedData>().props.auth.user;

  return (
    <>
      {(() => {
        switch (active) {
          case SettingLabelSidebar.Profile:
            return <DeleteUser />;
          case SettingLabelSidebar.TwoFactor:
            return (
              <Badge
                className={cn(
                  twoFactorEnabled
                    ? "bg-green-700 text-white dark:bg-green-600"
                    : "bg-destructive hover:bg-destructive/90 dark:bg-red-700 dark:text-white dark:hover:bg-red-800",
                )}
              >
                {twoFactorEnabled ? "Activado" : "Desactivado"}
              </Badge>
            );
          default:
            return null;
        }
      })()}
    </>
  );
}

export default SettingsViewHeader;
