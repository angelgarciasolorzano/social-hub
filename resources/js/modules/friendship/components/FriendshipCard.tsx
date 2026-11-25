import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { ImageIcon } from "lucide-react";

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

  const isFriend = friendshipState === "friends";

  const viewProfileButton = (
    <Button asChild className={cn(isFriend && "flex-1")} size="sm" variant="outline">
      <Link href={show(user.id)}>{t("friendship/friends.card.view_profile")}</Link>
    </Button>
  );

  const friendshipButton = (
    <FriendshipButton
      friendshipId={friendshipId}
      friendshipState={friendshipState}
      fullWidth={!isFriend}
      onChange={onFriendshipChange}
      userId={user.id}
      userName={user.name}
    />
  );

  return (
    <article className="flex flex-col overflow-hidden rounded-xl border bg-card text-card-foreground">
      <div aria-hidden="true" className="h-16 bg-muted">
        {user.coverImage !== null ? (
          <img alt="" className="size-full object-cover" loading="lazy" src={user.coverImage} />
        ) : (
          <div className="flex size-full items-center justify-center text-muted-foreground/60">
            <ImageIcon className="size-5" />
          </div>
        )}
      </div>

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

        {isFriend ? (
          <div className="mt-auto flex flex-wrap items-start gap-2">
            {viewProfileButton}

            {friendshipButton}
          </div>
        ) : (
          <div className="mt-auto flex flex-col gap-2">
            {viewProfileButton}

            {friendshipButton}
          </div>
        )}
      </div>
    </article>
  );
}

export default FriendshipCard;
