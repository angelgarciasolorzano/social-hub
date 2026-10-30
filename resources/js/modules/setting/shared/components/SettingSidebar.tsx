import type { ComponentProps, JSX } from "react";

import { Link, usePage } from "@inertiajs/react";

import { BookOpen, Bot, Command, SquareTerminal } from "lucide-react";

import HomeController from "@/shared/wayfinder/actions/App/Home/Controllers/HomeController";
import { edit as editAccountSettings } from "@/shared/wayfinder/actions/App/User/Modules/AccountSettings/Controllers/AccountSettingsController";
import { appearance } from "@/shared/wayfinder/actions/App/User/Preferences/Controllers/PreferenceController";

import { index as devicesIndex } from "@/shared/wayfinder/routes/setting/security/trusted-devices";
import { index as indexTwoFactorAuthentication } from "@/shared/wayfinder/routes/setting/security/two-factor-authentication";

import {
  Sidebar,
  SidebarContent,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
} from "@/shared/components/shadcn/ui/sidebar";

import type { SharedData } from "@/shared/types";

import { SettingSidebarNavMain } from "./SettingSidebarNavMain";

const settingNavigationGroups = [
  {
    title: "setting/common.sidebar.account",
    url: "#",
    icon: SquareTerminal,
    isActive: true,
    items: [
      {
        title: "setting/common.sidebar.profile",
        url: editAccountSettings.url(),
      },
    ],
  },
  {
    title: "setting/common.sidebar.preferences",
    url: "#",
    icon: Bot,
    items: [
      {
        title: "setting/common.sidebar.appearance",
        url: appearance.url(),
      },
    ],
  },
  {
    title: "setting/common.sidebar.security",
    url: "#",
    icon: BookOpen,
    items: [
      {
        title: "setting/common.sidebar.two_factor",
        url: indexTwoFactorAuthentication.url(),
      },
      {
        title: "setting/common.sidebar.trusted_devices",
        url: devicesIndex.url(),
      },
    ],
  },
];

export function SettingSidebar({ ...props }: ComponentProps<typeof Sidebar>): JSX.Element {
  const { name: appName } = usePage<SharedData>().props;

  return (
    <Sidebar collapsible="icon" {...props}>
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton asChild size="lg">
              <Link href={HomeController.url()} viewTransition>
                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                  <Command className="size-4" />
                </div>

                <span className="flex-1 truncate text-left text-sm leading-tight font-medium">
                  {appName}
                </span>
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        <SettingSidebarNavMain navigationGroups={settingNavigationGroups} />
      </SidebarContent>

      <SidebarRail />
    </Sidebar>
  );
}
