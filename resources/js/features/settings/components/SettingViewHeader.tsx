import { SettingLabelSidebar } from "../data/settingsSidebarItems";
import { settingsViewItems } from "../data/settingsViewItems";

interface SettingViewHeaderProps {
  active: SettingLabelSidebar;
}

function SettingViewHeader({ active }: SettingViewHeaderProps) {
  return (
    <header className="space-y-2">
      <h2 className="text-lg leading-none font-semibold">{settingsViewItems[active].label}</h2>
      <p className="text-sm text-muted-foreground">{settingsViewItems[active].description}</p>
    </header>
  );
}

export default SettingViewHeader;
