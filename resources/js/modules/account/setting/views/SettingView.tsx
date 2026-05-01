import { cn } from "@/shared/lib/utils";

import type { SettingLabelSidebarValue } from "../data/settingSidebarItems";
import { SettingLabelSidebar } from "../data/settingSidebarItems";
import SettingAppearanceView from "./SettingAppearanceView";
import SettingPasswordView from "./SettingPasswordView";
import SettingProfileView from "./SettingProfileView";
import SettingTwoFactorView from "./SettingTwoFactorView";

interface SettingsViewProps {
  active: SettingLabelSidebarValue;
}

function SettingsView({ active }: SettingsViewProps) {
  return (
    <main className={cn("my-4 flex-1", active !== SettingLabelSidebar.TwoFactor ? "my-4" : "my-1")}>
      {(() => {
        switch (active) {
          case SettingLabelSidebar.Appearance:
            return <SettingAppearanceView />;
          case SettingLabelSidebar.Password:
            return <SettingPasswordView />;
          case SettingLabelSidebar.Profile:
            return <SettingProfileView />;
          case SettingLabelSidebar.TwoFactor:
            return <SettingTwoFactorView />;
          default:
            return <div>Perfil View</div>;
        }
      })()}
    </main>
  );
}

export default SettingsView;
