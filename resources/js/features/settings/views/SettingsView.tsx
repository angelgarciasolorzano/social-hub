import { SettingLabelSidebar } from "../data/settingsSidebarItems";
import Appearance from "./Appearance";
import Password from "./Password";
import ProfileEdit from "./ProfileEdit";

interface SettingsViewProps {
  active: SettingLabelSidebar;
}

function SettingsView({ active }: SettingsViewProps) {
  return (
    <main className="mt-4 flex-1">
      {(() => {
        switch (active) {
          case SettingLabelSidebar.Perfil:
            return <ProfileEdit />;
          case SettingLabelSidebar.Contraseña:
            return <Password />;
          case SettingLabelSidebar.TwoFactor:
            return <div>Two Factor Authentication View</div>;
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
