import { SettingLabelSidebar } from "../data/settingsSidebarItems";
import { settingsViewItems } from "../data/settingsViewItems";

interface SettingViewHeaderProps {
  active: SettingLabelSidebar;
}

function SettingsViewHeader({ active }: SettingViewHeaderProps) {
  const Icon = settingsViewItems[active].icon;
  return (
    <header className="space-y-2 rounded-md border p-3 shadow-xs">
      <h2 className="flex gap-2 text-lg leading-none font-semibold text-primary">
        <Icon />

        {settingsViewItems[active].label}
      </h2>
      <p className="text-sm text-muted-foreground">{settingsViewItems[active].description}</p>
    </header>
  );
}

export default SettingsViewHeader;
