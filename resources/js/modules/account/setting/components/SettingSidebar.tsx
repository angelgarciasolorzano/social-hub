import type { Dispatch, SetStateAction } from "react";

import { Button } from "@/shared/components/ui/button";

import type { SettingLabelSidebarValue } from "../data/settingSidebarItems";
import { settingSidebarItems } from "../data/settingSidebarItems";

interface SettingsSidebarProps {
  active: SettingLabelSidebarValue;
  setActive: Dispatch<SetStateAction<SettingLabelSidebarValue>>;
}

function SettingsSidebar({ active, setActive }: SettingsSidebarProps) {
  return (
    <aside className="flex h-full w-60 flex-col items-center border-r">
      <nav className="w-full pr-2">
        <ul className="space-y-4">
          {settingSidebarItems.map((item, index) => (
            <li key={index}>
              <Button
                className="w-full cursor-pointer justify-start focus-visible:ring-0"
                onClick={() => setActive(item.label)}
                variant={active === item.label ? "default" : "ghost"}
              >
                <item.icon />
                {item.label}
              </Button>
            </li>
          ))}
        </ul>
      </nav>
    </aside>
  );
}

export default SettingsSidebar;
