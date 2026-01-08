import { cn } from "@/lib/utils";

import { SettingLabelSidebar } from "../data/settingsSidebarItems";
import Appearance from "./Appearance";
import Password from "./Password";
import ProfileEdit from "./ProfileEdit";
import TwoFactor from "./TwoFactor";

interface SettingsViewProps {
  active: SettingLabelSidebar;
}

function SettingsView({ active }: SettingsViewProps) {
  return (
    <main className={cn("my-4 flex-1", active !== SettingLabelSidebar.TwoFactor ? "my-4" : "my-1")}>
      {(() => {
        switch (active) {
          case SettingLabelSidebar.Appearance:
            return <Appearance />;
          case SettingLabelSidebar.Password:
            return <Password />;
          case SettingLabelSidebar.Profile:
            return <ProfileEdit />;
          case SettingLabelSidebar.TwoFactor:
            return <TwoFactor />;
          default:
            return <div>Perfil View</div>;
        }
      })()}
    </main>
  );
}

export default SettingsView;
