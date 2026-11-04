import type { JSX } from "react";

import { Deferred, Head, InfiniteScroll, usePage } from "@inertiajs/react";

import { HomeLayout } from "@/modules/home";

import { Button } from "@/shared/components/shadcn/ui/button";
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import { useTranslation } from "@/shared/hooks";

import type { SharedData } from "@/shared/types";

import NotificationListItem from "./components/NotificationListItem";
import useNotificationHistory from "./hooks/useNotificationHistory";
import type { Notification } from "./types/notification";

interface NotificationCollection {
  data: Notification[];
}

interface NotificationsProps {
  notifications: NotificationCollection;
}

function Notifications({ notifications }: NotificationsProps): JSX.Element {
  const { t } = useTranslation();
  const {
    auth: { user },
    unreadNotificationsCount,
  } = usePage<SharedData>().props;

  return (
    <HomeLayout>
      <Head title={t("notification/center.page.title")} />

      <main className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-2 py-4 sm:px-4">
        <header className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight">
            {t("notification/center.page.title")}
          </h1>
          <p className="text-sm text-muted-foreground">
            {t("notification/center.history.description")}
          </p>
        </header>

        <Deferred
          data="unreadNotificationsCount"
          fallback={
            <div
              aria-label={t("notification/center.history.loading")}
              className="space-y-3"
              role="status"
            >
              <Skeleton aria-hidden="true" className="h-16 rounded-xl bg-muted" />
              <Skeleton aria-hidden="true" className="h-24 rounded-xl bg-muted" />
              <Skeleton aria-hidden="true" className="h-24 rounded-xl bg-muted" />
            </div>
          }
        >
          <NotificationHistory
            initialUnreadCount={unreadNotificationsCount ?? 0}
            notifications={notifications}
            userId={user.id}
          />
        </Deferred>
      </main>
    </HomeLayout>
  );
}

interface NotificationHistoryProps {
  initialUnreadCount: number;
  notifications: NotificationCollection;
  userId: number;
}

function NotificationHistory({
  initialUnreadCount,
  notifications: notificationCollection,
  userId,
}: NotificationHistoryProps): JSX.Element {
  const { t, tChoice } = useTranslation();

  const {
    isMarkingAllAsRead,
    isMarkingAnyNotification,
    isMarkingRead,
    isNotificationRead,
    markAllNotificationsAsRead,
    markNotificationAsRead,
    notifications,
    unreadCount,
  } = useNotificationHistory({
    initialUnreadCount,
    pageNotifications: notificationCollection.data,
    userId,
  });

  return (
    <>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p aria-atomic="true" aria-live="polite" className="text-sm text-muted-foreground">
          {tChoice("notification/center.bell.unread_count", unreadCount, {
            count: unreadCount,
          })}
        </p>

        {unreadCount > 0 && (
          <Button
            disabled={isMarkingAllAsRead || isMarkingAnyNotification}
            onClick={() => {
              void markAllNotificationsAsRead();
            }}
            type="button"
            variant="outline"
          >
            {t("notification/center.actions.mark_all_read")}
          </Button>
        )}
      </div>

      <InfiniteScroll
        buffer={200}
        data="notifications"
        itemsElement="#notification-history-list"
        onlyNext
        preserveUrl
      >
        {({ loadingNext }) => (
          <>
            <ul
              aria-label={t("notification/center.history.list_label")}
              className="flex flex-col gap-3"
              id="notification-history-list"
            >
              {notifications.length === 0 ? (
                <li>
                  <div className="rounded-xl border border-dashed px-6 py-12 text-center">
                    <h2 className="text-lg font-medium">
                      {t("notification/center.history.empty_title")}
                    </h2>
                    <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                      {t("notification/center.history.empty_description")}
                    </p>
                  </div>
                </li>
              ) : (
                notifications.map((notification) => (
                  <NotificationListItem
                    isMarkingRead={isMarkingRead(notification.id)}
                    isRead={isNotificationRead(notification)}
                    key={notification.id}
                    notification={notification}
                    onMarkAsRead={(notificationId) => {
                      void markNotificationAsRead(notificationId);
                    }}
                  />
                ))
              )}
            </ul>

            {loadingNext && (
              <p
                aria-live="polite"
                className="py-4 text-center text-sm text-muted-foreground"
                role="status"
              >
                {t("notification/center.history.loading")}
              </p>
            )}
          </>
        )}
      </InfiniteScroll>
    </>
  );
}

export default Notifications;
