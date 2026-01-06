import { usePage } from "@inertiajs/react";

import { cn } from "@/lib/utils";

import DeleteUser from "@/components/delete-user";
import { Badge } from "@/components/ui/badge";

import { SharedData } from "@/types";

import { SettingLabelSidebar } from "../data/settingsSidebarItems";
import { settingsViewItems } from "../data/settingsViewItems";

interface SettingViewHeaderProps {
  active: SettingLabelSidebar;
}

function SettingsViewHeader({ active }: SettingViewHeaderProps) {
  const Icon = settingsViewItems[active].icon;

  return (
    <header className="space-y-2 rounded-md border p-3 shadow-xs">
      <div className="flex items-center justify-between">
        <h2 className="flex gap-2 text-lg leading-none font-semibold text-primary">
          <Icon />

          {settingsViewItems[active].label}
        </h2>

        <SettingsViewHeaderAddon active={active} />
      </div>

      <p className="text-sm text-muted-foreground">{settingsViewItems[active].description}</p>
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
