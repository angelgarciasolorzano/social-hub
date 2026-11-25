import type { JSX } from "react";
import { useRef } from "react";

import { InfiniteScroll, usePage } from "@inertiajs/react";

import { useTranslation } from "@/shared/hooks";

import type { FriendshipListItem } from "../types/friendshipList";
import type { FriendshipState } from "../types/friendshipSearch";
import FriendshipCard from "./FriendshipCard";
import FriendshipCardSkeleton from "./FriendshipCardSkeleton";

const GRID_CLASS_NAME = "grid grid-cols-1 gap-4 @lg/friends:grid-cols-2 @4xl/friends:grid-cols-3";

const TAB_SKELETON_COUNT = 6;

const NEXT_PAGE_SKELETON_COUNT = 3;

interface FriendshipCardGridProps {
  friendshipListItems: FriendshipListItem[];
  isLoadingTab: boolean;
  onFriendshipChange: (
    friendshipListItem: FriendshipListItem,
    friendshipState: FriendshipState,
  ) => void;
}

function FriendshipCardGrid(props: FriendshipCardGridProps): JSX.Element {
  const { friendshipListItems, isLoadingTab, onFriendshipChange } = props;

  const { t } = useTranslation();

  const { scrollProps } = usePage();

  const itemsRef = useRef<HTMLDivElement | null>(null);

  const hasNextPage = (scrollProps?.["friendships"]?.nextPage ?? null) !== null;

  if (isLoadingTab) {
    return <FriendshipCardSkeleton className={GRID_CLASS_NAME} count={TAB_SKELETON_COUNT} />;
  }

  return (
    <InfiniteScroll
      buffer={200}
      data="friendships"
      itemsElement={itemsRef}
      loading={
        <FriendshipCardSkeleton
          className={`mt-4 ${GRID_CLASS_NAME}`}
          count={NEXT_PAGE_SKELETON_COUNT}
          label={t("friendship/friends.states.loading_more")}
        />
      }
      onlyNext
      preserveUrl
    >
      <div className={GRID_CLASS_NAME} ref={itemsRef}>
        {friendshipListItems.map((friendshipListItem) => (
          <FriendshipCard
            friendshipListItem={friendshipListItem}
            key={friendshipListItem.friendshipId}
            onFriendshipChange={(friendshipState) => {
              onFriendshipChange(friendshipListItem, friendshipState);
            }}
          />
        ))}
      </div>

      {!hasNextPage && friendshipListItems.length > 0 && (
        <p className="mt-6 text-center text-sm text-muted-foreground">
          {t("friendship/friends.states.end_of_list")}
        </p>
      )}
    </InfiniteScroll>
  );
}

export default FriendshipCardGrid;
