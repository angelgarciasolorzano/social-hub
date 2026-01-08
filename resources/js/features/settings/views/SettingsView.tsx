import { cn } from "@/lib/utils";

import { SettingLabelSidebar } from "../data/settingsSidebarItems";
import SettingsAppearanceView from "./SettingsAppearanceView";
import SettingsPasswordView from "./SettingsPasswordView";
import SettingsProfileEditView from "./SettingsProfileEditView";
import SettingsTwoFactorView from "./SettingsTwoFactorView";

interface SettingsViewProps {
  active: SettingLabelSidebar;
}

function SettingsView({ active }: SettingsViewProps) {
  return (
    <main className={cn("my-4 flex-1", active !== SettingLabelSidebar.TwoFactor ? "my-4" : "my-1")}>
      {(() => {
        switch (active) {
          case SettingLabelSidebar.Appearance:
            return <SettingsAppearanceView />;
          case SettingLabelSidebar.Password:
            return <SettingsPasswordView />;
          case SettingLabelSidebar.Profile:
            return <SettingsProfileEditView />;
          case SettingLabelSidebar.TwoFactor:
            return <SettingsTwoFactorView />;
          default:
            return <div>Perfil View</div>;
        }
      })()}
    </main>
  );
}

export default SettingsView;
