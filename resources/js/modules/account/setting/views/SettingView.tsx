import { cn } from "@/shared/lib/utils";

import SettingPasswordView from "../../../setting/modules/password/EditPassword";
import SettingAppearanceView from "../../../setting/modules/preference/Appearance";
import SettingProfileView from "../../../setting/modules/profile/EditProfile";
import SettingTwoFactorView from "../../../setting/modules/twoFactor/TwoFactorAuthentication";
import type { SettingLabelSidebarValue } from "../data/settingSidebarItems";
import { SettingLabelSidebar } from "../data/settingSidebarItems";

interface SettingsViewProps {
  active: SettingLabelSidebarValue;
}

function SettingView({ active }: SettingsViewProps) {
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

export default SettingView;
