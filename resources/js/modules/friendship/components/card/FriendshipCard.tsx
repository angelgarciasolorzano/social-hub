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

import type { FriendshipListItem } from "../../types/friendshipList";
import type { FriendshipState } from "../../types/friendshipSearch";
import FriendshipButton from "../ui/FriendshipButton";

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

  const hasCoverImage = user.coverImage !== null;

  const viewProfileButton = (
    <Button asChild className="flex-1" size="sm" variant="outline">
      <Link href={show(user.id)}>{t("friendship/friends.card.view_profile")}</Link>
    </Button>
  );

  const friendshipButton = (
    <FriendshipButton
      friendshipId={friendshipId}
      friendshipState={friendshipState}
      friendsSince={isFriend ? since : null}
      mutualFriendsCount={mutualFriendsCount}
      onChange={onFriendshipChange}
      profilePicture={user.profilePicture}
      userId={user.id}
      userName={user.name}
    />
  );

  return (
    <article className="flex flex-col overflow-hidden rounded-xl border bg-card text-card-foreground shadow-xs">
      <div className="relative aspect-[16/10] bg-muted">
        {hasCoverImage ? (
          <img
            alt=""
            aria-hidden="true"
            className="absolute inset-0 size-full object-cover"
            loading="lazy"
            src={user.coverImage ?? undefined}
          />
        ) : (
          <div
            aria-hidden="true"
            className="absolute inset-0 flex items-center justify-center pb-16 text-muted-foreground/60"
          >
            <ImageIcon className="size-6" />
          </div>
        )}

        {hasCoverImage && (
          <div
            aria-hidden="true"
            className="absolute inset-0 bg-linear-to-t from-black/80 via-black/35 via-40% to-transparent to-65%"
          />
        )}

        <div className="absolute inset-x-4 bottom-3.5 flex items-center gap-3">
          <Avatar className={cn("size-11 ring-2", hasCoverImage ? "ring-white/85" : "ring-card")}>
            <AvatarImage
              alt={t("friendship/friends.card.profile_picture_alt", { name: user.name })}
              src={validImage(user.profilePicture, profilePicturePlaceholder)}
            />

            <AvatarFallback>{user.name.charAt(0).toUpperCase()}</AvatarFallback>
          </Avatar>

          <div className="min-w-0 space-y-0.5">
            <h2
              className={cn(
                "truncate text-base font-semibold",
                hasCoverImage ? "text-white" : "text-foreground",
              )}
            >
              <Link
                className={cn(
                  "rounded-sm hover:underline focus-visible:ring-2 focus-visible:outline-none",
                  hasCoverImage ? "focus-visible:ring-white/80" : "focus-visible:ring-ring",
                )}
                href={show(user.id)}
              >
                {user.name}
              </Link>
            </h2>

            {sinceText !== null && (
              <p
                className={cn(
                  "truncate text-xs",
                  hasCoverImage ? "text-white/80" : "text-muted-foreground",
                )}
              >
                {sinceText}
              </p>
            )}
          </div>
        </div>
      </div>

      <div className="flex flex-1 flex-col gap-3 p-4">
        {mutualFriendsCount > 0 && (
          <p className="text-xs text-muted-foreground">
            {tChoice("friendship/friends.card.mutual_friends", mutualFriendsCount, {
              count: mutualFriendsCount,
            })}
          </p>
        )}

        <div className="mt-auto flex flex-wrap items-start gap-2">
          {viewProfileButton}

          {friendshipButton}
        </div>
      </div>
    </article>
  );
}

export default FriendshipCard;
