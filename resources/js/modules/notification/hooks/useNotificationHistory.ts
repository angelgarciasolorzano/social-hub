import { useCallback, useMemo, useState } from "react";

import { useHttp } from "@inertiajs/react";

import { toast } from "sonner";

import {
  markAllAsRead,
  markAsRead,
} from "@/shared/wayfinder/actions/App/Modules/Notification/Controllers/NotificationController";

import { useTranslation } from "@/shared/hooks";

import { useNotificationStore } from "../stores/notificationStore";
import type { Notification } from "../types/notification";

interface UseNotificationHistoryParams {
  initialUnreadCount: number;
  pageNotifications: Notification[];
  userId: number;
}

interface UseNotificationHistoryReturn {
  isMarkingAllAsRead: boolean;
  isMarkingAnyNotification: boolean;
  isMarkingRead: (notificationId: string) => boolean;
  isNotificationRead: (notification: Notification) => boolean;
  markAllNotificationsAsRead: () => Promise<void>;
  markNotificationAsRead: (notificationId: string) => Promise<void>;
  notifications: Notification[];
  unreadCount: number;
}

function useNotificationHistory({
  initialUnreadCount,
  pageNotifications,
  userId,
}: UseNotificationHistoryParams): UseNotificationHistoryReturn {
  const { t } = useTranslation();
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

  const notifications = useMemo<Notification[]>(() => {
    const realtimeNotificationsById = new Map(
      realtimeNotifications.map((notification) => [notification.id, notification]),
    );

    const pageNotificationIds = new Set<string>();

    const uniquePageNotifications = pageNotifications.flatMap((notification) => {
      if (pageNotificationIds.has(notification.id)) {
        return [];
      }

      pageNotificationIds.add(notification.id);

      return [realtimeNotificationsById.get(notification.id) ?? notification];
    });

    const newRealtimeNotifications = realtimeNotifications.filter(
      (notification) => !pageNotificationIds.has(notification.id),
    );

    return [...newRealtimeNotifications, ...uniquePageNotifications];
  }, [pageNotifications, realtimeNotifications]);

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
      const notification = notifications.find(
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
      notifications,
      post,
      storeUserId,
      t,
      unreadCount,
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
            ...notifications
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
    notifications,
    post,
    storeUserId,
    t,
    unreadCount,
    userId,
  ]);

  const isMarkingRead = useCallback<(notificationId: string) => boolean>(
    (notificationId) => isMarkingAllAsRead || markingNotificationIds.has(notificationId),
    [isMarkingAllAsRead, markingNotificationIds],
  );

  return {
    isMarkingAllAsRead,
    isMarkingAnyNotification: markingNotificationIds.size > 0,
    isMarkingRead,
    isNotificationRead,
    markAllNotificationsAsRead,
    markNotificationAsRead,
    notifications,
    unreadCount,
  };
}

export default useNotificationHistory;
