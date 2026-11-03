import type { JSX } from "react";
import { useCallback, useMemo, useState } from "react";

import { Deferred, Head, InfiniteScroll, useHttp, usePage } from "@inertiajs/react";

import { toast } from "sonner";

import { HomeLayout } from "@/modules/home";

import {
  markAllAsRead,
  markAsRead,
} from "@/shared/wayfinder/actions/App/Modules/Notification/Controllers/NotificationController";

import { Button } from "@/shared/components/shadcn/ui/button";

import { useTranslation } from "@/shared/hooks";

import type { SharedData } from "@/shared/types";

import NotificationListItem from "./components/NotificationListItem";
import { useNotificationStore } from "./stores/notificationStore";
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
              <div aria-hidden="true" className="h-16 animate-pulse rounded-xl bg-muted" />
              <div aria-hidden="true" className="h-24 animate-pulse rounded-xl bg-muted" />
              <div aria-hidden="true" className="h-24 animate-pulse rounded-xl bg-muted" />
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
  notifications,
  userId,
}: NotificationHistoryProps): JSX.Element {
  const { t, tChoice } = useTranslation();
  const { post } = useHttp({});

  const storeUserId = useNotificationStore((state) => state.authenticatedUserId);

  const realtimeNotifications = useNotificationStore((state) =>
    state.authenticatedUserId === userId ? state.notifications : [],
  );

  const storeUnreadCount = useNotificationStore((state) =>
    state.authenticatedUserId === userId ? state.unreadCount : null,
  );

  const initializeForUser = useNotificationStore((state) => state.initializeForUser);

  const markStoredNotificationAsRead = useNotificationStore(
    (state) => state.markNotificationAsRead,
  );

  const markStoredNotificationsAsRead = useNotificationStore(
    (state) => state.markAllNotificationsAsRead,
  );

  const unreadCount =
    storeUserId === userId ? (storeUnreadCount ?? initialUnreadCount) : initialUnreadCount;

  const [readNotificationIds, setReadNotificationIds] = useState<Set<string>>(() => new Set());

  const [markingNotificationIds, setMarkingNotificationIds] = useState<Set<string>>(
    () => new Set(),
  );

  const [allReadBefore, setAllReadBefore] = useState<number | null>(null);
  const [isMarkingAllAsRead, setIsMarkingAllAsRead] = useState<boolean>(false);

  const uniqueNotifications = useMemo<Notification[]>(() => {
    const realtimeNotificationsById = new Map(
      realtimeNotifications.map((notification) => [notification.id, notification]),
    );

    const pageNotificationIds = new Set<string>();

    const pageNotifications = notifications.data.flatMap((notification) => {
      if (pageNotificationIds.has(notification.id)) {
        return [];
      }

      pageNotificationIds.add(notification.id);

      return [realtimeNotificationsById.get(notification.id) ?? notification];
    });

    const newRealtimeNotifications = realtimeNotifications.filter(
      (notification) => !pageNotificationIds.has(notification.id),
    );

    return [...newRealtimeNotifications, ...pageNotifications];
  }, [notifications.data, realtimeNotifications]);

  const isNotificationRead = useCallback<(notification: Notification) => boolean>(
    (notification) => {
      if (notification.readAt !== null || readNotificationIds.has(notification.id)) {
        return true;
      }

      return (
        allReadBefore !== null &&
        notification.createdAt !== null &&
        Date.parse(notification.createdAt) <= allReadBefore
      );
    },
    [allReadBefore, readNotificationIds],
  );

  const markNotificationAsRead = useCallback<(notificationId: string) => Promise<void>>(
    async (notificationId) => {
      const notification = uniqueNotifications.find(
        (candidateNotification) => candidateNotification.id === notificationId,
      );

      if (notification === undefined || isNotificationRead(notification) || isMarkingAllAsRead) {
        return;
      }

      setMarkingNotificationIds((currentIds) => new Set(currentIds).add(notificationId));

      try {
        await post(markAsRead({ notificationId }).url);

        setReadNotificationIds((currentIds) => new Set(currentIds).add(notificationId));

        if (storeUserId !== userId) {
          initializeForUser(userId, unreadCount);
        }

        markStoredNotificationAsRead(notificationId, unreadCount);

        toast.success(t("notification/center.toasts.marked_read"));
      } catch {
        return;
      } finally {
        setMarkingNotificationIds((currentIds) => {
          const nextIds = new Set(currentIds);

          nextIds.delete(notificationId);

          return nextIds;
        });
      }
    },
    [
      initializeForUser,
      isMarkingAllAsRead,
      isNotificationRead,
      markStoredNotificationAsRead,
      post,
      storeUserId,
      t,
      unreadCount,
      uniqueNotifications,
      userId,
    ],
  );

  const markAllNotificationsAsRead = useCallback<() => Promise<void>>(async () => {
    if (isMarkingAllAsRead || markingNotificationIds.size > 0) {
      return;
    }

    setIsMarkingAllAsRead(true);

    try {
      await post(markAllAsRead().url);

      const readAt = new Date().toISOString();
      const readAtTimestamp = Date.parse(readAt);

      setAllReadBefore(readAtTimestamp);

      setReadNotificationIds(
        (currentIds) =>
          new Set([
            ...currentIds,
            ...uniqueNotifications
              .filter(
                (notification) =>
                  notification.createdAt === null ||
                  Date.parse(notification.createdAt) <= readAtTimestamp,
              )
              .map((notification) => notification.id),
          ]),
      );

      if (storeUserId !== userId) {
        initializeForUser(userId, unreadCount);
      }

      markStoredNotificationsAsRead(readAt);

      toast.success(t("notification/center.toasts.all_marked_read"));
    } catch {
      return;
    } finally {
      setIsMarkingAllAsRead(false);
    }
  }, [
    initializeForUser,
    isMarkingAllAsRead,
    markingNotificationIds.size,
    markStoredNotificationsAsRead,
    post,
    storeUserId,
    t,
    unreadCount,
    uniqueNotifications,
    userId,
  ]);

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
            disabled={isMarkingAllAsRead || markingNotificationIds.size > 0}
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
              {uniqueNotifications.length === 0 ? (
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
                uniqueNotifications.map((notification) => (
                  <NotificationListItem
                    isMarkingRead={
                      markingNotificationIds.has(notification.id) || isMarkingAllAsRead
                    }
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
