import DeleteUser from "@/components/delete-user";

import { settingsFooterItems } from "../data/settingsFooterItems";
import { SettingLabelSidebar } from "../data/settingsSidebarItems";

interface SettingsViewFooterProps {
  active: SettingLabelSidebar;
}

function SettingsViewFooter({ active }: SettingsViewFooterProps) {
  return (
    <footer className="border-t pt-4">
      {(() => {
        switch (active) {
          case SettingLabelSidebar.Perfil:
            return <DeleteUser />;
          default:
            return <SettingsViewFooterDefault />;
        }
      })()}
    </footer>
  );
}

function SettingsViewFooterDefault() {
  return (
    <div className="flex items-center justify-between">
      <small className="text-xs">
        &copy; {new Date().getFullYear()} Social Hub. All rights reserved.
      </small>

      <div className="flex items-center justify-center gap-4">
        {settingsFooterItems.map((item, index) => (
          <item.icon key={index} />
        ))}
      </div>

      <div className="text-xs">v1.0.0</div>
    </div>
  );
}

export default SettingsViewFooter;
