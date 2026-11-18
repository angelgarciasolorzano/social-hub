import type { JSX, SubmitEvent } from "react";
import { useState } from "react";

import { Link, useHttp } from "@inertiajs/react";

import { IoIosSearch } from "react-icons/io";
import { LuCircleUserRound, LuUserRoundCheck, LuUserRoundPlus } from "react-icons/lu";

import { Loader2Icon } from "lucide-react";

import {
  search,
  store,
} from "@/shared/wayfinder/actions/App/Modules/Friendship/Controllers/FriendshipController";

import { show } from "@/shared/wayfinder/routes/profile";

import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/shadcn/ui/avatar";
import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/shared/components/shadcn/ui/dialog";
import { Input } from "@/shared/components/shadcn/ui/input";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/shared/components/shadcn/ui/tooltip";

import { validImage } from "@/shared/lib";

import { profilePicturePlaceholder } from "@/shared/assets";

import type {
  FriendSearchPage,
  FriendSearchResult,
  FriendshipActionResponse,
  FriendshipState,
} from "../../types/friendSearch";

const SEARCH_FAILED_MESSAGE = "No se pudo completar la búsqueda. Inténtalo de nuevo.";

const FRIEND_REQUEST_FAILED_MESSAGE = "No se pudo enviar la solicitud. Inténtalo de nuevo.";

function HomeSearchHeader(): JSX.Element {
  const [searchResults, setSearchResults] = useState<FriendSearchResult[]>([]);
  const [nextPageUrl, setNextPageUrl] = useState<null | string>(null);
  const [hasSearched, setHasSearched] = useState<boolean>(false);
  const [searchError, setSearchError] = useState<null | string>(null);

  const searchRequest = useHttp<{ q: string }, FriendSearchPage>(() => ({ q: "" }));
  const nextPageRequest = useHttp<Record<string, never>, FriendSearchPage>(() => ({}));

  const failSearch = (): false => {
    setSearchError(SEARCH_FAILED_MESSAGE);

    return false;
  };

  const submitSearch = (event: SubmitEvent<HTMLFormElement>): void => {
    event.preventDefault();
    setSearchError(null);

    searchRequest
      .get(search.url(), {
        onSuccess: (searchPage) => {
          setSearchResults(searchPage.data);
          setNextPageUrl(searchPage.next_page_url);
          setHasSearched(true);
        },
        onHttpException: (response) => (response.status === 422 ? undefined : failSearch()),
        onNetworkError: failSearch,
      })
      .catch(() => undefined);
  };

  const loadNextPage = (): void => {
    if (nextPageUrl === null || nextPageRequest.processing) return;

    setSearchError(null);

    nextPageRequest
      .get(nextPageUrl, {
        onSuccess: (searchPage) => {
          setSearchResults((currentResults) => [...currentResults, ...searchPage.data]);
          setNextPageUrl(searchPage.next_page_url);
        },
        onHttpException: failSearch,
        onNetworkError: failSearch,
      })
      .catch(() => undefined);
  };

  return (
    <div>
      <Dialog>
        <Tooltip>
          <DialogTrigger asChild>
            <TooltipTrigger asChild>
              <IoIosSearch className="h-8 w-8 cursor-pointer text-muted-foreground" />
            </TooltipTrigger>
          </DialogTrigger>

          <TooltipContent>Buscar amigos</TooltipContent>
        </Tooltip>

        <DialogContent className="flex max-h-[90%] min-h-[80%] max-w-[90%] min-w-[70%] flex-col">
          <DialogHeader>
            <DialogTitle>Buscar amigos</DialogTitle>

            <DialogDescription>
              Encuentra a tus amigos y comparte tus pensamientos
            </DialogDescription>

            <form className="mt-1.5 flex items-start gap-2" onSubmit={submitSearch}>
              <div className="flex flex-1 flex-col gap-1">
                <Input
                  aria-invalid={searchRequest.errors.q !== undefined}
                  name="q"
                  onChange={(event) => {
                    searchRequest.setData("q", event.target.value);
                  }}
                  placeholder="Buscar amigos"
                  type="text"
                  value={searchRequest.data.q}
                />

                {searchRequest.errors.q && (
                  <p className="text-sm text-destructive">{searchRequest.errors.q}</p>
                )}
              </div>

              <Button disabled={searchRequest.processing} type="submit">
                {searchRequest.processing ? (
                  <>
                    <Loader2Icon className="h-4 w-4 animate-spin" />
                    Buscando...
                  </>
                ) : (
                  "Buscar"
                )}
              </Button>
            </form>
          </DialogHeader>

          <div className="flex h-[90%] flex-1 flex-col gap-4 overflow-y-auto pr-2">
            {searchError && (
              <p className="text-sm text-destructive" role="alert">
                {searchError}
              </p>
            )}

            {hasSearched && searchResults.length === 0 && !searchError && (
              <p className="text-sm text-muted-foreground">
                No encontramos a nadie con ese nombre.
              </p>
            )}

            {searchResults.map((searchResult) => (
              <SearchResultRow key={searchResult.id} searchResult={searchResult} />
            ))}

            {nextPageUrl !== null && (
              <Button
                className="self-center"
                disabled={nextPageRequest.processing}
                onClick={loadNextPage}
                variant="outline"
              >
                {nextPageRequest.processing ? (
                  <Loader2Icon className="h-4 w-4 animate-spin" />
                ) : (
                  "Cargar más"
                )}
              </Button>
            )}
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}

interface SearchResultRowProps {
  searchResult: FriendSearchResult;
}

function SearchResultRow({ searchResult }: SearchResultRowProps): JSX.Element {
  return (
    <div className="flex items-center justify-between px-2 py-2">
      <div className="flex items-center gap-2">
        <Avatar className="h-12 w-12">
          <AvatarImage
            alt="Foto de perfil"
            src={validImage(searchResult.profilePicture, profilePicturePlaceholder)}
          />

          <AvatarFallback>{searchResult.name.charAt(0).toUpperCase()}</AvatarFallback>
        </Avatar>

        <div className="space-y-1.5">
          <h4 className="text-sm font-semibold">{searchResult.name}</h4>

          {searchResult.mutualFriendsCount > 0 && (
            <p className="text-xs font-medium text-muted-foreground">
              {searchResult.mutualFriendsCount === 1
                ? "1 amigo en común"
                : `${searchResult.mutualFriendsCount} amigos en común`}
            </p>
          )}
        </div>
      </div>

      <div className="flex gap-2">
        <FriendshipBadge
          initialFriendshipState={searchResult.friendshipState}
          userId={searchResult.id}
        />

        <Badge asChild className="cursor-pointer text-sm" variant="outline">
          <Link href={show(searchResult.id)}>
            <LuCircleUserRound className="h-4 w-4" />
            Ver perfil
          </Link>
        </Badge>
      </div>
    </div>
  );
}

interface FriendshipBadgeProps {
  initialFriendshipState: FriendshipState;
  userId: number;
}

/**
 * Sends a friend request when there is no relation yet, and otherwise shows the current one.
 * Accepting, declining or canceling belong to the friends page (SOC-84).
 */
function FriendshipBadge({ initialFriendshipState, userId }: FriendshipBadgeProps): JSX.Element {
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
            setRequestError(FRIEND_REQUEST_FAILED_MESSAGE);
          } else {
            setFriendshipState(friendshipActionResponse.state);
            setRequestError(friendshipActionResponse.message);
          }

          return false;
        },
        onNetworkError: () => {
          setRequestError(FRIEND_REQUEST_FAILED_MESSAGE);

          return false;
        },
      })
      .catch(() => undefined);
  };

  return (
    <div className="flex flex-col items-end gap-1">
      {friendshipState === "none" ? (
        <Badge
          aria-disabled={friendRequest.processing}
          asChild
          className="cursor-pointer text-sm dark:text-white/80"
          variant="secondary"
        >
          <button disabled={friendRequest.processing} onClick={sendFriendRequest} type="button">
            {friendRequest.processing ? (
              <Loader2Icon className="h-4 w-4 animate-spin" />
            ) : (
              <LuUserRoundPlus className="h-4 w-4" />
            )}
            Agregar amigo
          </button>
        </Badge>
      ) : (
        <Badge className="text-sm" variant="secondary">
          <LuUserRoundCheck className="h-4 w-4" />
          {friendshipStateLabel(friendshipState)}
        </Badge>
      )}

      {requestError && (
        <span className="max-w-56 text-right text-xs text-destructive" role="alert">
          {requestError}
        </span>
      )}
    </div>
  );
}

/**
 * The text of a relation that already exists.
 */
function friendshipStateLabel(friendshipState: Exclude<FriendshipState, "none">): string {
  switch (friendshipState) {
    case "friends":
      return "Amigos";
    case "request_received":
      return "Te envió una solicitud";
    case "request_sent":
      return "Solicitud enviada";
  }
}

/**
 * Read the body of a refused action, or null when the server answered something else (a 403, 429 or 500).
 */
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

export default HomeSearchHeader;
