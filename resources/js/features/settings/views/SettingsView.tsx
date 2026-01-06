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
          case SettingLabelSidebar.Perfil:
            return <ProfileEdit />;
          case SettingLabelSidebar.Contraseña:
            return <Password />;
          case SettingLabelSidebar.TwoFactor:
            return <TwoFactor />;
          case SettingLabelSidebar.Apariencia:
            return <Appearance />;
          default:
            return <div>Perfil View</div>;
        }
      })()}
    </main>
  );
}

export default SettingsView;
