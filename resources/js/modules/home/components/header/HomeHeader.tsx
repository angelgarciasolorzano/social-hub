import { useModal } from "@/shared/hooks/useModal";

import HomeNotificationHeader from "./HomeNotificationHeader";
import HomePostHeader from "./HomePostHeader";
import HomeProfileHeader from "./HomeProfileHeader";
import HomeSearchHeader from "./HomeSearchHeader";

function HomeHeader() {
  return (
    <div className="flex items-center justify-between border-b border-gray-200 px-3 py-3.5">
      <h3 className="scroll-m-20 text-2xl font-semibold tracking-tight">SOCIAL HUB</h3>

      <HeaderAction />
    </div>
  );
}

function HeaderAction() {
  const { open: isOpenModal, setOpen: setIsOpenModal } = useModal();

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
