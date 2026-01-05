import { useState } from "react";

import { Button } from "@/components/ui/button";

import { settingSidebarItems } from "../data/settingSidebarItems";

function SettingSidebar() {
  const [active, setActive] = useState<string>("Perfil");

  return (
    <aside className="flex h-full w-60 flex-col items-center border-r">
      <nav className="w-full pr-2">
        <ul className="space-y-4">
          {settingSidebarItems.map((item, index) => (
            <li key={index}>
              <Button
                variant={active === item.label ? "default" : "ghost"}
                className="w-full cursor-pointer justify-start focus-visible:ring-0"
                onClick={() => setActive(item.label)}
              >
                <item.icon className="mr-2 inline-block" />
                {item.label}
              </Button>
            </li>
          ))}
        </ul>
      </nav>
    </aside>
  );
}

export default SettingSidebar;
