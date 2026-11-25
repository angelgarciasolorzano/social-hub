import type { JSX, MouseEvent } from "react";
import { useId } from "react";

import { Deferred, Link, usePage } from "@inertiajs/react";

import { ArrowRightIcon, UserPlusIcon } from "lucide-react";

import { index as friendshipsIndex } from "@/shared/wayfinder/routes/friendships";
import { show } from "@/shared/wayfinder/routes/profile";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/shadcn/ui/avatar";
import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Card,
  CardAction,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/shared/components/shadcn/ui/card";
import {
  Item,
  ItemActions,
  ItemContent,
  ItemDescription,
  ItemMedia,
  ItemTitle,
} from "@/shared/components/shadcn/ui/item";
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useDateTime, useTranslation } from "@/shared/hooks";

import { validImage } from "@/shared/lib";
import { iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

import type { SharedData } from "@/shared/types";

import { profilePicturePlaceholder } from "@/shared/assets";

import type { FriendshipListItem } from "../../types/friendshipList";
import type { FriendshipState } from "../../types/friendshipSearch";
import { reloadFriendshipDependentProps } from "../../utils/reloadFriendshipDependentProps";
import FriendshipButton from "../ui/FriendshipButton";

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
    <Card aria-labelledby={titleId} className="gap-4 py-4 shadow-xs" role="region">
      <CardHeader className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4">
        <div
          aria-hidden="true"
          className={cn(
            "flex size-8 shrink-0 items-center justify-center rounded-md",
            iconColorVariants.blue.iconBgClass,
          )}
        >
          <UserPlusIcon className={cn("size-4", iconColorVariants.blue.iconFgClass)} />
        </div>

        <CardTitle className="flex items-center gap-2 text-sm whitespace-nowrap" id={titleId}>
          {t("friendship/friends.requests.title")}

          <Badge aria-hidden="true" className="tabular-nums" variant="secondary">
            {friendRequests.pendingCount}
          </Badge>
        </CardTitle>

        <CardAction className="-mr-2 ml-auto self-center">
          <Button asChild size="sm" variant="ghost">
            <Link href={friendshipsIndex({ query: { tab: "received" } })} onClick={handleViewAll}>
              {tChoice("friendship/friends.requests.view_all", friendRequests.pendingCount, {
                count: friendRequests.pendingCount,
              })}

              <ArrowRightIcon aria-hidden="true" />
            </Link>
          </Button>
        </CardAction>
      </CardHeader>

      <CardContent className="px-4">
        <ul
          aria-label={t("friendship/friends.requests.label")}
          className="grid gap-3 @2xl/friends:grid-cols-2"
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
      </CardContent>
    </Card>
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
    <Item asChild className="bg-muted/30" size="sm" variant="outline">
      <li>
        <ItemMedia>
          <Avatar className="size-10">
            <AvatarImage
              alt={t("friendship/friends.card.profile_picture_alt", { name: user.name })}
              src={validImage(user.profilePicture, profilePicturePlaceholder)}
            />

            <AvatarFallback>{user.name.charAt(0).toUpperCase()}</AvatarFallback>
          </Avatar>
        </ItemMedia>

        <ItemContent className="min-w-40 gap-0.5">
          <ItemTitle className="max-w-full">
            <Link className="truncate font-semibold hover:underline" href={show(user.id)}>
              {user.name}
            </Link>
          </ItemTitle>

          {details.length > 0 && (
            <ItemDescription className="truncate text-xs">{details.join(" · ")}</ItemDescription>
          )}
        </ItemContent>

        <ItemActions>
          <FriendshipButton
            friendshipId={friendRequest.friendshipId}
            friendshipState={friendRequest.friendshipState}
            onChange={onFriendshipChange}
            userId={user.id}
            userName={user.name}
          />
        </ItemActions>
      </li>
    </Item>
  );
}

function FriendRequestsHighlightSkeleton(): JSX.Element {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-4 rounded-xl border p-4" role="status">
      <span className="sr-only">{t("friendship/friends.states.loading")}</span>

      <Skeleton aria-hidden="true" className="h-8 w-48" />

      <div aria-hidden="true" className="grid gap-3 @2xl/friends:grid-cols-2">
        <Skeleton className="h-16 rounded-md" />

        <Skeleton className="h-16 rounded-md" />
      </div>
    </div>
  );
}

export default FriendRequestsHighlight;
