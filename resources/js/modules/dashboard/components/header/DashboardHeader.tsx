import { useModal } from "@/shared/hooks/useModal";

import NotificationsHeader from "./DashboardNotificationsHeader";
import PostHeader from "./DashboardPostHeader";
import ProfileHeader from "./DashboardProfileHeader";
import SearchHeader from "./DashboardSearchHeader";

function DashboardHeader() {
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
      <SearchHeader />
      <PostHeader isOpenModal={isOpenModal} setIsOpenModal={setIsOpenModal} />
      <NotificationsHeader />
      <ProfileHeader />
    </div>
  );
}

export default DashboardHeader;
