import type { JSX } from "react";
import { useState } from "react";

import { Link, useHttp } from "@inertiajs/react";

import { LuCircleUserRound, LuUserRoundCheck, LuUserRoundPlus } from "react-icons/lu";

import { Loader2Icon } from "lucide-react";

import { store } from "@/shared/wayfinder/actions/App/Modules/Friendship/Controllers/FriendshipController";

import { show } from "@/shared/wayfinder/routes/profile";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/shadcn/ui/avatar";
import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";

import { useTranslation } from "@/shared/hooks";

import { validImage } from "@/shared/lib";

import { profilePicturePlaceholder } from "@/shared/assets";

import type {
  FriendSearchResult,
  FriendshipActionResponse,
  FriendshipState,
} from "../types/friendshipSearch";

interface FriendshipSearchResultRowProps {
  searchResult: FriendSearchResult;
}

function FriendshipSearchResultRow({ searchResult }: FriendshipSearchResultRowProps): JSX.Element {
  const { t, tChoice } = useTranslation();

  return (
    <article className="flex flex-col gap-3 rounded-xl border p-3 sm:flex-row sm:items-center sm:justify-between">
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
              {tChoice("friendship/search.result.mutual_friends", searchResult.mutualFriendsCount, {
                count: searchResult.mutualFriendsCount,
              })}
            </p>
          )}
        </div>
      </div>

      <div className="flex shrink-0 items-start gap-2 self-end sm:self-auto">
        <FriendshipSearchButton
          initialFriendshipState={searchResult.friendshipState}
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
    </article>
  );
}

interface FriendshipSearchButtonProps {
  initialFriendshipState: FriendshipState;
  userId: number;
  userName: string;
}

function FriendshipSearchButton({
  initialFriendshipState,
  userId,
  userName,
}: FriendshipSearchButtonProps): JSX.Element {
  const { t } = useTranslation();
  const [friendshipState, setFriendshipState] = useState<FriendshipState>(initialFriendshipState);
  const [requestError, setRequestError] = useState<null | string>(null);

  const friendRequest = useHttp<Record<string, never>, FriendshipActionResponse>(() => ({}));

  const sendFriendRequest = (): void => {
    if (friendRequest.processing) return;

    setRequestError(null);

    friendRequest
      .post(store.url(userId), {
        onSuccess: (friendshipActionResponse) => {
          setFriendshipState(friendshipActionResponse.state);
        },
        onHttpException: (response) => {
          const friendshipActionResponse = parseFriendshipActionResponse(response.data);

          if (friendshipActionResponse === null) {
            setRequestError(t("friendship/search.button.error"));
          } else {
            setFriendshipState(friendshipActionResponse.state);
            setRequestError(friendshipActionResponse.message);
          }

          return false;
        },
        onNetworkError: () => {
          setRequestError(t("friendship/search.button.error"));

          return false;
        },
      })
      .catch(() => undefined);
  };

  return (
    <div className="flex flex-col items-end gap-1">
      {friendshipState === "none" ? (
        <Button
          aria-label={
            friendRequest.processing
              ? t("friendship/search.button.sending", { name: userName })
              : undefined
          }
          disabled={friendRequest.processing}
          onClick={sendFriendRequest}
          size="sm"
          type="button"
          variant="secondary"
        >
          {friendRequest.processing ? (
            <Loader2Icon aria-hidden="true" className="size-4 animate-spin" />
          ) : (
            <LuUserRoundPlus aria-hidden="true" className="size-4" />
          )}
          {t("friendship/search.button.add")}
        </Button>
      ) : (
        <Badge className="h-8 px-3 text-sm" variant="secondary">
          <LuUserRoundCheck aria-hidden="true" className="size-4" />
          {t(friendshipStateLabelKey(friendshipState))}
        </Badge>
      )}

      {requestError && (
        <span className="max-w-64 text-right text-xs text-destructive" role="alert">
          {requestError}
        </span>
      )}
    </div>
  );
}

function friendshipStateLabelKey(friendshipState: Exclude<FriendshipState, "none">): string {
  switch (friendshipState) {
    case "friends":
      return "friendship/search.button.friends";
    case "request_received":
      return "friendship/search.button.request_received";
    case "request_sent":
      return "friendship/search.button.request_sent";
  }
}

function parseFriendshipActionResponse(responseBody: string): FriendshipActionResponse | null {
  try {
    const parsedBody: unknown = JSON.parse(responseBody);

    if (
      typeof parsedBody === "object" &&
      parsedBody !== null &&
      "message" in parsedBody &&
      "state" in parsedBody &&
      typeof parsedBody.message === "string" &&
      typeof parsedBody.state === "string"
    ) {
      return parsedBody as FriendshipActionResponse;
    }
  } catch {
    return null;
  }

  return null;
}

export default FriendshipSearchResultRow;
