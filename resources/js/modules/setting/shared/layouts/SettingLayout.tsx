import type { JSX, PropsWithChildren } from "react";

import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/shared/components/shadcn/ui/sidebar";

import { SettingSidebar } from "../components/SettingSidebar";

export default function SettingLayout({ children }: PropsWithChildren): JSX.Element {
  return (
    <SidebarProvider>
      <SettingSidebar />

      <SidebarInset className="min-w-0">
        <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
          <div className="flex items-center gap-2 px-4">
            <SidebarTrigger className="-ml-1" />
          </div>
        </header>

        <div className="m-4 flex min-w-0 flex-1 flex-col p-4 pt-0">{children}</div>
      </SidebarInset>
    </SidebarProvider>
  );
}
