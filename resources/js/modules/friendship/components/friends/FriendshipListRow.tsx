import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { show } from "@/shared/wayfinder/routes/profile";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/shadcn/ui/avatar";

import { useDateTime, useTranslation } from "@/shared/hooks";

import { validImage } from "@/shared/lib";

import { profilePicturePlaceholder } from "@/shared/assets";

import type { FriendshipListItem } from "../../types/friendshipList";
import type { FriendshipState } from "../../types/friendshipSearch";
import FriendshipButton from "../button/FriendshipButton";

interface FriendshipListRowProps {
  friendshipListItem: FriendshipListItem;
  onFriendshipChange?: (friendshipState: FriendshipState, friendshipId: null | number) => void;
}

function FriendshipListRow(props: FriendshipListRowProps): JSX.Element {
  const { friendshipListItem, onFriendshipChange } = props;

  const { t } = useTranslation();

  const { formatFullDate, fromNow } = useDateTime();

  const { friendshipId, friendshipState, since, user } = friendshipListItem;

  const sinceText =
    since === null
      ? null
      : friendshipState === "friends"
        ? t("friendship/friends.row.friends_since", { date: formatFullDate(since) })
        : t("friendship/friends.row.request_sent_at", { date: fromNow(since) });

  return (
    <article className="@container rounded-xl border p-3">
      <div className="flex flex-col gap-3 @lg:flex-row @lg:items-center @lg:justify-between">
        <div className="flex min-w-0 items-center gap-3">
          <Avatar className="size-12">
            <AvatarImage
              alt={t("friendship/friends.row.profile_picture_alt", { name: user.name })}
              src={validImage(user.profilePicture, profilePicturePlaceholder)}
            />

            <AvatarFallback>{user.name.charAt(0).toUpperCase()}</AvatarFallback>
          </Avatar>

          <div className="min-w-0 space-y-1">
            <h2 className="truncate text-sm font-semibold">
              <Link className="hover:underline" href={show(user.id)}>
                {user.name}
              </Link>
            </h2>

            {sinceText !== null && <p className="text-xs text-muted-foreground">{sinceText}</p>}
          </div>
        </div>

        <div className="flex shrink-0 items-start @lg:self-auto">
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

export default FriendshipListRow;
