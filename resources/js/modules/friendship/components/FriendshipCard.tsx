import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { show } from "@/shared/wayfinder/routes/profile";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/shadcn/ui/avatar";
import { Button } from "@/shared/components/shadcn/ui/button";

import { useDateTime, useTranslation } from "@/shared/hooks";

import { validImage } from "@/shared/lib";
import { cn } from "@/shared/lib/utils";

import { profilePicturePlaceholder } from "@/shared/assets";

import type { FriendshipListItem } from "../types/friendshipList";
import type { FriendshipState } from "../types/friendshipSearch";
import FriendshipButton from "./FriendshipButton";

const COVER_COLORS = [
  "bg-blue-200 dark:bg-blue-900/60",
  "bg-pink-200 dark:bg-pink-900/60",
  "bg-green-200 dark:bg-green-900/60",
  "bg-amber-200 dark:bg-amber-900/60",
  "bg-violet-200 dark:bg-violet-900/60",
  "bg-cyan-200 dark:bg-cyan-900/60",
] as const;

interface FriendshipCardProps {
  friendshipListItem: FriendshipListItem;
  onFriendshipChange?: (friendshipState: FriendshipState, friendshipId: null | number) => void;
}

function FriendshipCard(props: FriendshipCardProps): JSX.Element {
  const { friendshipListItem, onFriendshipChange } = props;

  const { t, tChoice } = useTranslation();

  const { formatFullDate, fromNow } = useDateTime();

  const { friendshipId, friendshipState, mutualFriendsCount, since, user } = friendshipListItem;

  const sinceText =
    since === null
      ? null
      : friendshipState === "friends"
        ? t("friendship/friends.row.friends_since", { date: formatFullDate(since) })
        : t("friendship/friends.row.request_sent_at", { date: fromNow(since) });

  const coverColorFor = (userId: number): string => {
    return COVER_COLORS[userId % COVER_COLORS.length] ?? COVER_COLORS[0];
  };

  return (
    <article className="flex flex-col overflow-hidden rounded-xl border bg-card text-card-foreground">
      <div aria-hidden="true" className={cn("h-16", coverColorFor(user.id))} />

      <div className="-mt-7 flex flex-1 flex-col gap-3 px-4 pb-4">
        <Avatar className="size-14 border-3 border-card">
          <AvatarImage
            alt={t("friendship/friends.card.profile_picture_alt", { name: user.name })}
            src={validImage(user.profilePicture, profilePicturePlaceholder)}
          />

          <AvatarFallback>{user.name.charAt(0).toUpperCase()}</AvatarFallback>
        </Avatar>

        <div className="min-w-0 space-y-0.5">
          <h2 className="truncate text-[15px] font-semibold">
            <Link className="hover:underline" href={show(user.id)}>
              {user.name}
            </Link>
          </h2>

          {sinceText !== null && <p className="text-xs text-muted-foreground">{sinceText}</p>}
        </div>

        {mutualFriendsCount > 0 && (
          <p className="text-xs text-foreground/80">
            {tChoice("friendship/friends.card.mutual_friends", mutualFriendsCount, {
              count: mutualFriendsCount,
            })}
          </p>
        )}

        <div className="mt-auto flex flex-wrap items-start gap-2">
          <Button asChild className="flex-1" size="sm" variant="outline">
            <Link href={show(user.id)}>{t("friendship/friends.card.view_profile")}</Link>
          </Button>

          <FriendshipButton
            friendshipId={friendshipId}
            friendshipState={friendshipState}
            onChange={onFriendshipChange}
            userId={user.id}
            userName={user.name}
          />
        </div>
      </div>
    </article>
  );
}

export default FriendshipCard;
