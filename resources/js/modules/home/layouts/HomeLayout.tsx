import type { PropsWithChildren, Ref } from "react";

import HomeHeader from "../components/header/HomeHeader";
import HomeSidebarLeft from "../components/sidebar/HomeSidebarLeft";
import HomeSidebarRight from "../components/sidebar/HomeSidebarRight";

interface HomeLayoutProps extends PropsWithChildren {
  contentRef?: Ref<HTMLDivElement>;
}

function HomeLayout({ children, contentRef }: HomeLayoutProps) {
  return (
    <div className="flex h-screen flex-col">
      <HomeHeader />

      <div className="flex flex-1 overflow-hidden">
        <HomeSidebarLeft />

        <div className="flex flex-1 flex-col overflow-y-auto px-4 py-4" ref={contentRef}>
          {children}
        </div>

        <HomeSidebarRight />
      </div>
    </div>
  );
}

export default HomeLayout;
