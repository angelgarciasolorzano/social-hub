import type { JSX } from "react";

import { useDialog } from "@/shared/hooks/useDialog";

import HomeMobileNavigation from "../sidebar/HomeMobileNavigation";
import HomeNotificationHeader from "./HomeNotificationHeader";
import HomePostHeader from "./HomePostHeader";
import HomeProfileHeader from "./HomeProfileHeader";
import HomeSearchHeader from "./HomeSearchHeader";

function HomeHeader(): JSX.Element {
  return (
    <div className="flex items-center justify-between border-b border-gray-200 px-3 py-3.5">
      <div className="flex items-center gap-1">
        <HomeMobileNavigation />

        <h3 className="scroll-m-20 text-xl font-semibold tracking-tight whitespace-nowrap sm:text-2xl">
          SOCIAL HUB
        </h3>
      </div>

      <HeaderAction />
    </div>
  );
}

function HeaderAction(): JSX.Element {
  const { open: isOpenModal, setOpen: setIsOpenModal } = useDialog();

  return (
    <div className="flex items-center gap-4">
      <HomeSearchHeader />
      <HomePostHeader isOpenModal={isOpenModal} setIsOpenModal={setIsOpenModal} />
      <HomeNotificationHeader />
      <HomeProfileHeader />
    </div>
  );
}

export default HomeHeader;
