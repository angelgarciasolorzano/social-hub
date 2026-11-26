import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { LuCircleUserRound } from "react-icons/lu";

import { show } from "@/shared/wayfinder/routes/profile";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/shadcn/ui/avatar";
import { Button } from "@/shared/components/shadcn/ui/button";

import { useTranslation } from "@/shared/hooks";

import { validImage } from "@/shared/lib";

import { profilePicturePlaceholder } from "@/shared/assets";

import type { FriendSearchResult } from "../../types/friendshipSearch";
import FriendshipButton from "./FriendshipButton";

interface FriendshipSearchResultRowProps {
  searchResult: FriendSearchResult;
}

function FriendshipSearchResultRow({ searchResult }: FriendshipSearchResultRowProps): JSX.Element {
  const { t, tChoice } = useTranslation();

  return (
    <article className="@container rounded-xl border p-3">
      <div className="flex flex-col gap-3 @lg:flex-row @lg:items-center @lg:justify-between">
        <div className="flex min-w-0 items-center gap-3">
          <Avatar className="size-12">
            <AvatarImage
              alt={t("friendship/search.result.profile_picture_alt", { name: searchResult.name })}
              src={validImage(searchResult.profilePicture, profilePicturePlaceholder)}
            />

            <AvatarFallback>{searchResult.name.charAt(0).toUpperCase()}</AvatarFallback>
          </Avatar>

          <div className="min-w-0 space-y-1">
            <h2 className="truncate text-sm font-semibold">{searchResult.name}</h2>

            {searchResult.mutualFriendsCount > 0 && (
              <p className="text-xs text-muted-foreground">
                {tChoice(
                  "friendship/search.result.mutual_friends",
                  searchResult.mutualFriendsCount,
                  {
                    count: searchResult.mutualFriendsCount,
                  },
                )}
              </p>
            )}
          </div>
        </div>

        <div className="flex shrink-0 items-start gap-2 @lg:self-auto">
          <FriendshipButton
            friendshipId={searchResult.friendshipId}
            friendshipState={searchResult.friendshipState}
            mutualFriendsCount={searchResult.mutualFriendsCount}
            profilePicture={searchResult.profilePicture}
            userId={searchResult.id}
            userName={searchResult.name}
          />

          <Button asChild size="sm" variant="outline">
            <Link href={show(searchResult.id)}>
              <LuCircleUserRound aria-hidden="true" className="size-4" />
              {t("friendship/search.result.view_profile")}
            </Link>
          </Button>
        </div>
      </div>
    </article>
  );
}

export default FriendshipSearchResultRow;
