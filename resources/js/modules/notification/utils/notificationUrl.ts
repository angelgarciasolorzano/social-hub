import { show as showPost } from "@/shared/wayfinder/actions/App/Modules/Post/Controllers/PostController";

import { index as friendshipsIndex } from "@/shared/wayfinder/routes/friendships";
import { show as showProfile } from "@/shared/wayfinder/routes/profile";

import type { Notification } from "../types/notification";

export interface NotificationLink {
  url: string;
  labelKey: string;
}

/**
 * Builds the page a notification leads to and the translation key of its action, from its type.
 * A received request opens the received tab of the friends page, an accepted one the profile of
 * whoever accepted it, and the others the related post with its comment thread.
 *
 * @param notification - Notification to link.
 * @returns The link, or `null` when its post or actor is no longer known.
 */
export function getNotificationLink(notification: Notification): NotificationLink | null {
  if (notification.type === "friend_request_received") {
    return {
      url: friendshipsIndex({ query: { tab: "received" } }).url,
      labelKey: "notification/center.actions.open_friend_requests",
    };
  }

  if (notification.type === "friend_request_accepted") {
    const actor = notification.actors[0];

    return actor === undefined
      ? null
      : { url: showProfile(actor.id).url, labelKey: "notification/center.actions.open_profile" };
  }

  const { commentId, postId } = notification.target;

  if (postId === null) {
    return null;
  }

  return {
    url: showPost(postId, {
      query:
        commentId === null
          ? { comments_post: postId }
          : { comments_post: postId, replies_comment: commentId },
    }).url,
    labelKey:
      commentId === null
        ? "notification/center.actions.open_post"
        : "notification/center.actions.open_comment",
  };
}
