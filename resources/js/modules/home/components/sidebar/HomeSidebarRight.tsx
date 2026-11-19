import type { JSX } from "react";

import FriendRequestsPanel from "@/modules/friendship/components/FriendRequestsPanel";

function HomeSidebarRight(): JSX.Element {
  return (
    <aside className="w-64 shrink-0 overflow-y-auto border-l px-3 py-4">
      <FriendRequestsPanel />
    </aside>
  );
}

export default HomeSidebarRight;
