import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { ChevronRight, type LucideIcon } from "lucide-react";

import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/shared/components/shadcn/ui/collapsible";
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from "@/shared/components/shadcn/ui/sidebar";

import { useTranslation } from "@/shared/hooks";

interface SettingSidebarNavLink {
  title: string;
  url: string;
}

type SettingSidebarNavGroup = SettingSidebarNavLink & {
  icon?: LucideIcon;
  isActive?: boolean;
  items?: SettingSidebarNavLink[];
};

interface SettingSidebarNavMainProps {
  navigationGroups: SettingSidebarNavGroup[];
}

export function SettingSidebarNavMain({
  navigationGroups,
}: SettingSidebarNavMainProps): JSX.Element {
  const { t } = useTranslation();

  return (
    <SidebarGroup>
      <SidebarGroupLabel>{t("setting/common.sidebar.group_label")}</SidebarGroupLabel>

      <SidebarMenu>
        {navigationGroups.map((navigationGroup) => (
          <Collapsible
            className="group/collapsible"
            asChild
            defaultOpen={navigationGroup.isActive}
            key={navigationGroup.title}
          >
            <SidebarMenuItem>
              <CollapsibleTrigger asChild>
                <SidebarMenuButton tooltip={t(navigationGroup.title)}>
                  {navigationGroup.icon && <navigationGroup.icon />}

                  <span>{t(navigationGroup.title)}</span>

                  <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                </SidebarMenuButton>
              </CollapsibleTrigger>

              <CollapsibleContent>
                <SidebarMenuSub>
                  {navigationGroup.items?.map((navigationLink) => (
                    <SidebarMenuSubItem key={navigationLink.title}>
                      <SidebarMenuSubButton asChild>
                        <Link href={navigationLink.url}>
                          <span>{t(navigationLink.title)}</span>
                        </Link>
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
