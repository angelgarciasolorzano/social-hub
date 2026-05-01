import { settingFooterItems } from "../data/settingFooterItems";
import { type SettingLabelSidebarValue } from "../data/settingSidebarItems";

interface SettingViewFooterProps {
  active: SettingLabelSidebarValue;
}

function SettingViewFooter({ active }: SettingViewFooterProps) {
  return (
    <footer className="border-t pt-4">
      {(() => {
        switch (active) {
          default:
            return <SettingViewFooterDefault />;
        }
      })()}
    </footer>
  );
}

function SettingViewFooterDefault() {
  return (
    <div className="flex items-center justify-between">
      <small className="text-xs">
        &copy; {new Date().getFullYear()} Social Hub. All rights reserved.
      </small>

      <div className="flex items-center justify-center gap-4">
        {settingFooterItems.map((item, index) => (
          <item.icon key={index} />
        ))}
      </div>

      <div className="text-xs">v1.0.0</div>
    </div>
  );
}

export default SettingViewFooter;
