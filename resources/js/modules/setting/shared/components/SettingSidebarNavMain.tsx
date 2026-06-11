import { ChevronRight, type LucideIcon } from "lucide-react";

import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/shared/components/ui/collapsible";
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from "@/shared/components/ui/sidebar";

interface SettingSidebarNavMainSubItems {
  title: string;
  url: string;
}

type SettingSidebarNavMainItems = Pick<SettingSidebarNavMainSubItems, "title" | "url"> & {
  icon?: LucideIcon;
  isActive?: boolean;
  items?: SettingSidebarNavMainSubItems[];
};

interface SettingSidebarNavMainProps {
  items: SettingSidebarNavMainItems[];
}

export function SettingSidebarNavMain({ items }: SettingSidebarNavMainProps) {
  return (
    <SidebarGroup>
      <SidebarGroupLabel>Configuración</SidebarGroupLabel>

      <SidebarMenu>
        {items.map((item) => (
          <Collapsible
            className="group/collapsible"
            asChild
            defaultOpen={item.isActive}
            key={item.title}
          >
            <SidebarMenuItem>
              <CollapsibleTrigger asChild>
                <SidebarMenuButton tooltip={item.title}>
                  {item.icon && <item.icon />}

                  <span>{item.title}</span>

                  <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                </SidebarMenuButton>
              </CollapsibleTrigger>

              <CollapsibleContent>
                <SidebarMenuSub>
                  {item.items?.map((subItem) => (
                    <SidebarMenuSubItem key={subItem.title}>
                      <SidebarMenuSubButton asChild>
                        <a href={subItem.url}>
                          <span>{subItem.title}</span>
                        </a>
                      </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                  ))}
                </SidebarMenuSub>
              </CollapsibleContent>
            </SidebarMenuItem>
          </Collapsible>
        ))}
      </SidebarMenu>
    </SidebarGroup>
  );
}
