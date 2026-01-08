import { PropsWithChildren, useEffect } from "react";

import { router, usePage } from "@inertiajs/react";

import { toast } from "sonner";

import HomeHeader from "../components/header/HomeHeader";
import HomeSidebarLeft from "../components/sidebar/HomeSidebarLeft";
import HomeSidebarRight from "../components/sidebar/HomeSidebarRight";

function HomeLayout({ children }: PropsWithChildren) {
  const { props } = usePage();

  const notification = props.notification as {
    type: "success" | "error";
    message: string;
    action?: {
      label: string;
      url: string;
    };
  } | null;

  useEffect(() => {
    if (notification) {
      toast(notification.message, {
        action: notification.action && {
          label: notification.action.label,
          onClick: () => router.visit(notification.action!.url),
        },
      });
    }
  }, [notification]);

  return (
    <div className="flex h-screen flex-col">
      <HomeHeader />

      <div className="flex flex-1 overflow-hidden">
        <HomeSidebarLeft />
        <div className="flex flex-1 flex-col gap-4 overflow-y-auto">{children}</div>
        <HomeSidebarRight />
      </div>
    </div>
  );
}

export default HomeLayout;
