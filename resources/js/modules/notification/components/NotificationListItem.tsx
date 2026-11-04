import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { show as showPost } from "@/shared/wayfinder/actions/App/Modules/Post/Controllers/PostController";

import { Button } from "@/shared/components/shadcn/ui/button";

import { useDateTime, useTranslation } from "@/shared/hooks";

import { cn } from "@/shared/lib/utils";

import type { Notification } from "../types/notification";

interface NotificationListItemProps {
  isMarkingRead: boolean;
  isRead: boolean;
  notification: Notification;
  onMarkAsRead: (notificationId: string) => void;
}

function NotificationListItem({
  isMarkingRead,
  isRead,
  notification,
  onMarkAsRead,
}: NotificationListItemProps): JSX.Element {
  const { formatFullDate, fromNow } = useDateTime();
  const { t } = useTranslation();
  const postId = notification.target.postId;
  const commentId = notification.target.commentId;
  const postUrl =
    postId === null
      ? null
      : showPost(postId, {
          query:
            commentId === null
              ? { comments_post: postId }
              : { comments_post: postId, replies_comment: commentId },
        }).url;

  return (
    <li>
      <article
        className={cn(
          "flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-start sm:justify-between",
          !isRead && "border-primary/30 bg-primary/5",
        )}
      >
        <div className="flex min-w-0 flex-1 gap-3">
          <span
            aria-hidden="true"
            className={cn(
              "mt-1.5 size-2 shrink-0 rounded-full",
              isRead ? "bg-muted-foreground/40" : "bg-primary",
            )}
          />

          <div className="min-w-0 space-y-2">
            <p className="wrap-break-words text-sm font-medium">{notification.summary}</p>

            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
              <span>
                {t(
                  isRead ? "notification/center.status.read" : "notification/center.status.unread",
                )}
              </span>
              <span aria-hidden="true">·</span>
              <time
                dateTime={notification.createdAt ?? undefined}
                title={formatFullDate(notification.createdAt)}
              >
                {fromNow(notification.createdAt)}
              </time>
            </div>
          </div>
        </div>

        <div className="flex shrink-0 flex-wrap items-center gap-2 pl-5 sm:pl-0">
          {postUrl === null ? (
            <span className="text-sm text-muted-foreground">
              {t("notification/center.target.content_unavailable")}
            </span>
          ) : (
            <Link
              className="text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2"
              href={postUrl}
            >
              {t(
                commentId === null
                  ? "notification/center.actions.open_post"
                  : "notification/center.actions.open_comment",
              )}
            </Link>
          )}

          {!isRead && (
            <Button
              disabled={isMarkingRead}
              onClick={() => {
                onMarkAsRead(notification.id);
              }}
              type="button"
              variant="ghost"
              size="sm"
            >
              {t(
                isMarkingRead
                  ? "notification/center.status.marking_read"
                  : "notification/center.actions.mark_read",
              )}
            </Button>
          )}
        </div>
      </article>
    </li>
  );
}

export default NotificationListItem;
