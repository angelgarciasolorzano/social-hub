import type { PropsWithChildren } from "react";

import DashboardHeader from "../components/header/DashboardHeader";
import DashboardSidebarLeft from "../components/sidebar/DashboardSidebarLeft";
import DashboardSidebarRight from "../components/sidebar/DashboardSidebarRight";

function DashboardLayout({ children }: PropsWithChildren) {
  return (
    <div className="flex h-screen flex-col">
      <DashboardHeader />

      <div className="flex flex-1 overflow-hidden">
        <DashboardSidebarLeft />

        <div className="flex flex-1 flex-col gap-4 overflow-y-auto">{children}</div>

        <DashboardSidebarRight />
      </div>
    </div>
  );
}

export default DashboardLayout;
