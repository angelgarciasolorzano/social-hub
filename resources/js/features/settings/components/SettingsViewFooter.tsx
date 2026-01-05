import DeleteUser from "@/components/delete-user";

import { SettingLabelSidebar } from "../data/settingsSidebarItems";

interface SettingsViewFooterProps {
  active: SettingLabelSidebar;
}

function SettingsViewFooter({ active }: SettingsViewFooterProps) {
  return (
    <footer>
      {(() => {
        switch (active) {
          case SettingLabelSidebar.Perfil:
            return <DeleteUser />;
          default:
            return null;
        }
      })()}
    </footer>
  );
}

export default SettingsViewFooter;
