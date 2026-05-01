import type { PropsWithChildren, Ref } from "react";

import DashboardHeader from "../components/header/DashboardHeader";
import DashboardSidebarLeft from "../components/sidebar/DashboardSidebarLeft";
import DashboardSidebarRight from "../components/sidebar/DashboardSidebarRight";

interface DashboardLayoutProps extends PropsWithChildren {
  contentRef?: Ref<HTMLDivElement>;
}

function DashboardLayout({ children, contentRef }: DashboardLayoutProps) {
  return (
    <div className="flex h-screen flex-col">
      <DashboardHeader />

      <div className="flex flex-1 overflow-hidden">
        <DashboardSidebarLeft />

        <div className="flex flex-1 flex-col overflow-y-auto px-4 py-4" ref={contentRef}>
          {children}
        </div>

        <DashboardSidebarRight />
      </div>
    </div>
  );
}

export default DashboardLayout;
