import type { JSX, MouseEvent } from "react";
import { useId } from "react";

import { Link } from "@inertiajs/react";

import { IoIosNotificationsOutline } from "react-icons/io";

import { index as notificationsIndex } from "@/shared/wayfinder/actions/App/Modules/Notification/Controllers/NotificationController";

import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/shared/components/shadcn/ui/popover";
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useDateTime, useTranslation } from "@/shared/hooks";

import useNotificationDropdown from "../hooks/useNotificationDropdown";
import type { Notification } from "../types/notification";

function NotificationDropdown(): JSX.Element {
  const { t, tChoice } = useTranslation();
  const { formatFullDate, fromNow } = useDateTime();

  const popoverId = useId();
  const popoverTitleId = `${popoverId}-title`;

  const {
    getNotificationPostUrl,
    hasLoadError,
    isLoading,
    isOpen,
    markingNotificationId,
    notifications,
    onOpenChange,
    openNotification,
    markNotificationAsRead,
    unreadCount,
  } = useNotificationDropdown();

  const buttonLabel =
    unreadCount > 0
      ? tChoice("notification/center.bell.open_unread", unreadCount, { count: unreadCount })
      : t("notification/center.bell.open");

  return (
    <Popover onOpenChange={onOpenChange} open={isOpen}>
      <PopoverTrigger asChild>
        <Button
          aria-controls={popoverId}
          aria-label={buttonLabel}
          className="relative text-muted-foreground"
          size="icon"
          type="button"
          variant="ghost"
        >
          <IoIosNotificationsOutline aria-hidden="true" />

          {unreadCount > 0 && (
            <Badge
              aria-hidden="true"
              className="absolute -top-1 -right-1 min-w-5 border-background px-1 text-[10px]"
              variant="destructive"
            >
              {unreadCount > 99 ? "99+" : unreadCount}
            </Badge>
          )}
        </Button>
      </PopoverTrigger>

      <PopoverContent
        align="end"
        aria-labelledby={popoverTitleId}
        className="w-80 max-w-[calc(100vw-2rem)] overflow-hidden p-0"
        id={popoverId}
        sideOffset={8}
      >
        <div className="flex items-center justify-between gap-3 border-b px-4 py-3">
          <h2 className="text-sm font-semibold" id={popoverTitleId}>
            {t("notification/center.dropdown.title")}
          </h2>

          <Link
            className="shrink-0 text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2"
            href={notificationsIndex().url}
          >
            {t("notification/center.dropdown.view_all")}
          </Link>
        </div>

        <div className="max-h-[min(70vh,32rem)] overflow-y-auto">
          {isLoading && notifications.length === 0 ? (
            <div className="p-4" role="status">
              <span className="sr-only">{t("notification/center.history.loading")}</span>
              <div aria-hidden="true" className="flex flex-col gap-4">
                <div className="flex items-center gap-3">
                  <Skeleton className="size-2 rounded-full" />
                  <Skeleton className="h-4 flex-1" />
                </div>
                <div className="flex items-center gap-3">
                  <Skeleton className="size-2 rounded-full" />
                  <Skeleton className="h-4 flex-1" />
                </div>
                <div className="flex items-center gap-3">
                  <Skeleton className="size-2 rounded-full" />
                  <Skeleton className="h-4 flex-1" />
                </div>
              </div>
            </div>
          ) : notifications.length === 0 ? (
            <div className="px-4 py-8 text-center">
              {hasLoadError ? (
                <p className="text-sm text-muted-foreground">
                  {t("notification/center.dropdown.empty_description")}
                </p>
              ) : (
                <>
                  <h3 className="text-sm font-medium">
                    {t("notification/center.dropdown.empty_title")}
                  </h3>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {t("notification/center.dropdown.empty_description")}
                  </p>
                </>
              )}
            </div>
          ) : (
            <ul aria-label={t("notification/center.dropdown.title")}>
              {notifications.map((notification: Notification) => {
                const postUrl = getNotificationPostUrl(notification);
                const isRead = notification.readAt !== null;
                const isMarkingRead = markingNotificationId === notification.id;

                return (
                  <li className="border-b last:border-b-0" key={notification.id}>
                    <div className="flex items-start gap-3 px-4 py-3">
                      <span
                        aria-hidden="true"
                        className={`mt-1.5 size-2 shrink-0 rounded-full ${
                          isRead ? "bg-muted-foreground/40" : "bg-primary"
                        }`}
                      />

                      <div className="min-w-0 flex-1">
                        {postUrl === null ? (
                          <p className="wrap-break-words text-sm font-medium">
                            {notification.summary}
                          </p>
                        ) : (
                          <Link
                            className="wrap-break-words block text-left text-sm font-medium hover:text-primary focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2"
                            href={postUrl}
                            onClick={(event) => {
                              if (isModifiedClick(event)) {
                                return;
                              }

                              event.preventDefault();
                              void openNotification(notification);
                            }}
                          >
                            {notification.summary}
                          </Link>
                        )}

                        <div className="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                          <span className="sr-only">
                            {t(
                              isRead
                                ? "notification/center.status.read"
                                : "notification/center.status.unread",
                            )}
                          </span>

                          <time
                            dateTime={notification.createdAt ?? undefined}
                            title={formatFullDate(notification.createdAt)}
                          >
                            {fromNow(notification.createdAt)}
                          </time>
                        </div>

                        {postUrl === null && (
                          <p className="mt-1 text-xs text-muted-foreground">
                            {t("notification/center.target.content_unavailable")}
                          </p>
                        )}
                      </div>

                      {!isRead && (
                        <Button
                          disabled={markingNotificationId !== null}
                          onClick={() => {
                            void markNotificationAsRead(notification);
                          }}
                          size="xs"
                          type="button"
                          variant="ghost"
                        >
                          {t(
                            isMarkingRead
                              ? "notification/center.status.marking_read"
                              : "notification/center.actions.mark_read",
                          )}
                        </Button>
                      )}
                    </div>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}

function isModifiedClick(event: MouseEvent): boolean {
  return (
    event.defaultPrevented ||
    event.button !== 0 ||
    event.metaKey ||
    event.ctrlKey ||
    event.shiftKey ||
    event.altKey
  );
}

export default NotificationDropdown;
