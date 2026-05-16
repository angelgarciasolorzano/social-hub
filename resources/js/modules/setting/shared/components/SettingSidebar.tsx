import type * as React from "react";

import { Link } from "@inertiajs/react";

import { BookOpen, Bot, Command, SquareTerminal } from "lucide-react";

import HomeController from "@/shared/wayfinder/actions/App/Home/Controllers/HomeController";
import { edit } from "@/shared/wayfinder/actions/App/User/Controllers/ProfileController";

import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
} from "@/shared/components/ui/sidebar";

import { SettingSidebarNavMain } from "./SettingSidebarNavMain";
import { SettingSidebarNavUser } from "./SettingSidebarNavUser";

const data = {
  user: {
    name: "shadcn",
    email: "m@example.com",
    avatar: "/avatars/shadcn.jpg",
  },
  navMain: [
    {
      title: "Cuenta",
      url: "#",
      icon: SquareTerminal,
      isActive: true,
      items: [
        {
          title: "Perfil",
          url: edit.url(),
        },
      ],
    },
    {
      title: "Preferencias",
      url: "#",
      icon: Bot,
      items: [
        {
          title: "Apariencia",
          url: "#",
        },
      ],
    },
    {
      title: "Seguridad",
      url: "#",
      icon: BookOpen,
      items: [
        {
          title: "Autenticación en dos pasos",
          url: "#",
        },
      ],
    },
  ],
};

export function SettingSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
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

                <div className="grid flex-1 text-left text-sm leading-tight">
                  <span className="truncate font-medium">Acme Inc</span>

                  <span className="truncate text-xs">Enterprise</span>
                </div>
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        <SettingSidebarNavMain items={data.navMain} />
      </SidebarContent>

      <SidebarFooter>
        <SettingSidebarNavUser user={data.user} />
      </SidebarFooter>

      <SidebarRail />
    </Sidebar>
  );
}
