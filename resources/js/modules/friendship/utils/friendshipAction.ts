import {
  accept,
  cancel,
  decline,
  destroy,
  store,
} from "@/shared/wayfinder/actions/App/Modules/Friendship/Controllers/FriendshipController";

import type {
  FriendshipAction,
  FriendshipActionResponse,
  FriendshipState,
} from "../types/friendshipSearch";

/**
 * The Wayfinder URL of an action: sending targets the person, the others the pair's row, so they need
 * its id. Null when the row is unknown.
 *
 * @param friendshipAction The action to run.
 * @param userId The id of the other person, the target of a new request.
 * @param friendshipId The id of the pair's row, or null when there is none yet.
 * @returns The URL of the action, or null when it needs a row that is unknown.
 */
export function friendshipActionUrl(
  friendshipAction: FriendshipAction,
  userId: number,
  friendshipId: null | number,
): null | string {
  if (friendshipAction === "send") {
    return store.url(userId);
  }

  if (friendshipId === null) {
    return null;
  }

  switch (friendshipAction) {
    case "accept":
      return accept.url(friendshipId);
    case "cancel":
      return cancel.url(friendshipId);
    case "decline":
      return decline.url(friendshipId);
    case "remove":
      return destroy.url(friendshipId);
  }
}

/**
 * The state shown while the action is in flight, before the server answers.
 *
 * @param friendshipAction The action in flight.
 * @returns The state the friendship will have if the server accepts the action.
 */
export function optimisticFriendshipState(friendshipAction: FriendshipAction): FriendshipState {
  switch (friendshipAction) {
    case "accept":
      return "friends";
    case "send":
      return "request_sent";
    case "cancel":
    case "decline":
    case "remove":
      return "none";
  }
}

/**
 * The translation key of the accessible label shown while the action is in flight.
 *
 * @param friendshipAction The action in flight.
 * @returns The key of the label in `friendship/button.processing`.
 */
export function processingLabelKey(friendshipAction: FriendshipAction): string {
  switch (friendshipAction) {
    case "accept":
      return "friendship/button.processing.accepting";
    case "cancel":
      return "friendship/button.processing.canceling";
    case "decline":
      return "friendship/button.processing.declining";
    case "remove":
      return "friendship/button.processing.removing";
    case "send":
      return "friendship/button.processing.sending";
  }
}

/**
 * Read a refusal's body, which carries the stored state and the message; null for any other body
 * (a 429 from the throttle, an HTML error page).
 *
 * @param responseBody The raw body of the refused response.
 * @returns The stored state, the row id and the message, or null when the body has another shape.
 */
export function parseFriendshipActionResponse(
  responseBody: string,
): FriendshipActionResponse | null {
  try {
    const parsedBody: unknown = JSON.parse(responseBody);

    if (
      typeof parsedBody === "object" &&
      parsedBody !== null &&
      "message" in parsedBody &&
      "state" in parsedBody &&
      "friendshipId" in parsedBody &&
      typeof parsedBody.message === "string" &&
      typeof parsedBody.state === "string" &&
      (parsedBody.friendshipId === null || typeof parsedBody.friendshipId === "number")
    ) {
      return parsedBody as FriendshipActionResponse;
    }
  } catch {
    return null;
  }

  return null;
}
