import type { JSX } from "react";
import { useId } from "react";

import { Deferred, Link, router, usePage } from "@inertiajs/react";

import { index as friendshipsIndex } from "@/shared/wayfinder/routes/friendships";
import { show } from "@/shared/wayfinder/routes/profile";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/shadcn/ui/avatar";
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useTranslation } from "@/shared/hooks";

import { validImage } from "@/shared/lib";

import type { SharedData } from "@/shared/types";

import { profilePicturePlaceholder } from "@/shared/assets";

import type { FriendshipListItem } from "../types/friendshipList";
import FriendshipButton from "./FriendshipButton";

function FriendRequestsPanel(): JSX.Element {
  const { t } = useTranslation();

  const titleId = useId();

  return (
    <section aria-labelledby={titleId} className="space-y-3">
      <h2 className="text-sm font-semibold" id={titleId}>
        {t("home/sidebar.friend_requests.title")}
      </h2>

      <Deferred data="friendRequests" fallback={<FriendRequestsSkeleton />}>
        <FriendRequestsList />
      </Deferred>
    </section>
  );
}

function FriendRequestsList(): JSX.Element {
  const { t } = useTranslation();

  const { friendRequests } = usePage<SharedData>().props;

  const recentFriendRequests = friendRequests?.recent ?? [];

  const reloadFriendRequests = (): void => {
    router.reload({ only: ["friendRequests"] });
  };

  if (recentFriendRequests.length === 0) {
    return (
      <div className="rounded-lg border border-dashed px-3 py-6 text-center">
        <p className="text-sm font-medium">{t("home/sidebar.friend_requests.empty_title")}</p>

        <p className="mt-1 text-xs text-muted-foreground">
          {t("home/sidebar.friend_requests.empty_description")}
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <ul className="space-y-3">
        {recentFriendRequests.map((friendRequest) => (
          <FriendRequestItem
            friendRequest={friendRequest}
            key={friendRequest.friendshipId}
            onFriendshipChange={reloadFriendRequests}
          />
        ))}
      </ul>

      <Link
        className="block text-sm font-medium text-primary hover:underline"
        href={friendshipsIndex({ query: { tab: "received" } })}
      >
        {t("home/sidebar.friend_requests.view_all")}
      </Link>
    </div>
  );
}

interface FriendRequestItemProps {
  friendRequest: FriendshipListItem;
  onFriendshipChange: () => void;
}

function FriendRequestItem(props: FriendRequestItemProps): JSX.Element {
  const { friendRequest, onFriendshipChange } = props;

  const { t } = useTranslation();

  const { user } = friendRequest;

  return (
    <li className="space-y-2 rounded-lg border p-2">
      <div className="flex min-w-0 items-center gap-2">
        <Avatar className="size-8">
          <AvatarImage
            alt={t("home/sidebar.friend_requests.profile_picture_alt", { name: user.name })}
            src={validImage(user.profilePicture, profilePicturePlaceholder)}
          />

          <AvatarFallback>{user.name.charAt(0).toUpperCase()}</AvatarFallback>
        </Avatar>

        <Link className="truncate text-sm font-medium hover:underline" href={show(user.id)}>
          {user.name}
        </Link>
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

function FriendRequestsSkeleton(): JSX.Element {
  const { t } = useTranslation();

  return (
    <div aria-label={t("home/sidebar.friend_requests.loading")} className="space-y-3" role="status">
      <Skeleton aria-hidden="true" className="h-20 rounded-lg" />

      <Skeleton aria-hidden="true" className="h-20 rounded-lg" />
    </div>
  );
}

export default FriendRequestsPanel;
