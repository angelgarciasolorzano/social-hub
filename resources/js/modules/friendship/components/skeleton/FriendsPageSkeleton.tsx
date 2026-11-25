import type { JSX } from "react";

import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useTranslation } from "@/shared/hooks";

import { FRIENDSHIP_CARD_GRID_CLASS_NAME } from "../../constants/friendshipCardGrid";
import FriendRequestsHighlightSkeleton from "./FriendRequestsHighlightSkeleton";
import FriendshipCardSkeleton from "./FriendshipCardSkeleton";

const TAB_SKELETON_COUNT = 3;

const CARD_SKELETON_COUNT = 6;

function FriendsPageSkeleton(): JSX.Element {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-5" role="status">
      <span className="sr-only">{t("friendship/friends.states.loading")}</span>

      <div aria-hidden="true" className="flex flex-col gap-5">
        <FriendRequestsHighlightSkeleton />

        <div className="flex flex-col gap-3 pb-3 @4xl/friends:flex-row @4xl/friends:items-end @4xl/friends:justify-between">
          <div className="flex w-full gap-5 border-b pb-2.5 @4xl/friends:w-fit">
            {Array.from({ length: TAB_SKELETON_COUNT }, (_, position) => (
              <Skeleton className="h-5 w-24" key={position} />
            ))}
          </div>

          <div className="flex w-full flex-wrap gap-2 @4xl/friends:w-auto @4xl/friends:flex-nowrap">
            <Skeleton className="h-9 w-full @md/friends:w-auto @md/friends:flex-1 @4xl/friends:w-64 @4xl/friends:flex-none" />

            <Skeleton className="h-9 flex-1 @md/friends:w-32 @md/friends:flex-none" />

            <Skeleton className="size-9 shrink-0" />
          </div>
        </div>

        <FriendshipCardSkeleton
          className={FRIENDSHIP_CARD_GRID_CLASS_NAME}
          count={CARD_SKELETON_COUNT}
        />
      </div>
    </div>
  );
}

export default FriendsPageSkeleton;
