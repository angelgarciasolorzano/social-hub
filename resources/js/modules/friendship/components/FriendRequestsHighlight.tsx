import type { JSX, MouseEvent } from "react";
import { useId } from "react";

import { Deferred, Link, usePage } from "@inertiajs/react";

import { index as friendshipsIndex } from "@/shared/wayfinder/routes/friendships";
import { show } from "@/shared/wayfinder/routes/profile";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/shadcn/ui/avatar";
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useDateTime, useTranslation } from "@/shared/hooks";

import { validImage } from "@/shared/lib";

import type { SharedData } from "@/shared/types";

import { profilePicturePlaceholder } from "@/shared/assets";

import type { FriendshipListItem } from "../types/friendshipList";
import type { FriendshipState } from "../types/friendshipSearch";
import { reloadFriendshipDependentProps } from "../utils/reloadFriendshipDependentProps";
import FriendshipButton from "./FriendshipButton";

interface FriendRequestsHighlightProps {
  onViewAll?: () => void;
}

function FriendRequestsHighlight(props: FriendRequestsHighlightProps): JSX.Element {
  const { onViewAll } = props;

  return (
    <Deferred data="friendRequests" fallback={<FriendRequestsHighlightSkeleton />}>
      <FriendRequestsHighlightContent onViewAll={onViewAll} />
    </Deferred>
  );
}

function FriendRequestsHighlightContent(props: FriendRequestsHighlightProps): JSX.Element | null {
  const { onViewAll } = props;

  const { t, tChoice } = useTranslation();

  const titleId = useId();

  const { component, props: pageProps } = usePage<SharedData>();

  const friendRequests = pageProps.friendRequests;

  if (
    friendRequests === undefined ||
    friendRequests === null ||
    friendRequests.pendingCount === 0
  ) {
    return null;
  }

  const handleViewAll = (event: MouseEvent): void => {
    if (onViewAll === undefined) return;

    event.preventDefault();

    onViewAll();
  };

  return (
    <section
      aria-labelledby={titleId}
      className="flex flex-col gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3.5 dark:border-blue-900 dark:bg-blue-950/40"
    >
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-sm font-semibold text-blue-900 dark:text-blue-100" id={titleId}>
          {t("friendship/friends.requests.title")}
        </h2>

        <Link
          className="text-sm font-medium text-blue-700 hover:underline dark:text-blue-300"
          href={friendshipsIndex({ query: { tab: "received" } })}
          onClick={handleViewAll}
        >
          {tChoice("friendship/friends.requests.view_all", friendRequests.pendingCount, {
            count: friendRequests.pendingCount,
          })}
        </Link>
      </div>

      <ul
        aria-label={t("friendship/friends.requests.label")}
        className="grid gap-2.5 md:grid-cols-2"
      >
        {friendRequests.recent.map((friendRequest) => (
          <FriendRequestHighlightItem
            friendRequest={friendRequest}
            key={friendRequest.friendshipId}
            onFriendshipChange={(friendshipState) => {
              reloadFriendshipDependentProps(component, friendshipState);
            }}
          />
        ))}
      </ul>
    </section>
  );
}

interface FriendRequestHighlightItemProps {
  friendRequest: FriendshipListItem;
  onFriendshipChange: (friendshipState: FriendshipState) => void;
}

function FriendRequestHighlightItem(props: FriendRequestHighlightItemProps): JSX.Element {
  const { friendRequest, onFriendshipChange } = props;

  const { t, tChoice } = useTranslation();

  const { fromNow } = useDateTime();

  const { mutualFriendsCount, since, user } = friendRequest;

  const details = [
    since === null ? null : t("friendship/friends.row.request_sent_at", { date: fromNow(since) }),
    mutualFriendsCount > 0
      ? tChoice("friendship/friends.card.mutual_friends", mutualFriendsCount, {
          count: mutualFriendsCount,
        })
      : null,
  ].filter((detail) => detail !== null);

  return (
    <li className="flex flex-wrap items-center gap-2.5 rounded-lg bg-card px-3 py-2.5 text-card-foreground">
      <Avatar className="size-10">
        <AvatarImage
          alt={t("friendship/friends.card.profile_picture_alt", { name: user.name })}
          src={validImage(user.profilePicture, profilePicturePlaceholder)}
        />

        <AvatarFallback>{user.name.charAt(0).toUpperCase()}</AvatarFallback>
      </Avatar>

      <div className="flex min-w-40 flex-1 flex-col">
        <Link className="truncate text-sm font-semibold hover:underline" href={show(user.id)}>
          {user.name}
        </Link>

        {details.length > 0 && (
          <span className="truncate text-xs text-muted-foreground">{details.join(" · ")}</span>
        )}
      </div>

      <FriendshipButton
        friendshipId={friendRequest.friendshipId}
        friendshipState={friendRequest.friendshipState}
        onChange={onFriendshipChange}
        userId={user.id}
        userName={user.name}
      />
    </li>
  );
}

function FriendRequestsHighlightSkeleton(): JSX.Element {
  const { t } = useTranslation();

  return (
    <div className="grid gap-2.5 md:grid-cols-2" role="status">
      <span className="sr-only">{t("friendship/friends.states.loading")}</span>

      <Skeleton aria-hidden="true" className="h-16 rounded-xl" />

      <Skeleton aria-hidden="true" className="h-16 rounded-xl" />
    </div>
  );
}

export default FriendRequestsHighlight;
