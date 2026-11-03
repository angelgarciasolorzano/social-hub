import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import { router, useHttp, usePage } from "@inertiajs/react";

import { toast } from "sonner";

import {
  markAsRead,
  recent,
} from "@/shared/wayfinder/actions/App/Modules/Notification/Controllers/NotificationController";
import { show as showPost } from "@/shared/wayfinder/actions/App/Modules/Post/Controllers/PostController";

import { useTranslation } from "@/shared/hooks";

import type { SharedData } from "@/shared/types";

import { useNotificationStore } from "../stores/notificationStore";
import type { Notification } from "../types/notification";

const RECENT_NOTIFICATION_LIMIT = 5;

interface NotificationCollection {
  data: Notification[];
}

interface UseNotificationDropdownReturn {
  hasLoadError: boolean;
  isLoading: boolean;
  isOpen: boolean;
  markingNotificationId: string | null;
  notifications: Notification[];
  unreadCount: number;
  getNotificationPostUrl: (notification: Notification) => string | null;
  markNotificationAsRead: (notification: Notification) => Promise<boolean>;
  onOpenChange: (open: boolean) => void;
  openNotification: (notification: Notification) => Promise<void>;
}

/**
 * Load recent notifications on demand and keep them in sync with live Echo events.
 */
function useNotificationDropdown(): UseNotificationDropdownReturn {
  const { auth, unreadNotificationsCount } = usePage<SharedData>().props;
  const userId = auth.user.id;
  const { t } = useTranslation();

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

  const { get: getRecentNotifications } = useHttp<Record<string, never>, NotificationCollection>(
    {},
  );

  const { post: postMarkAsRead } = useHttp<Record<string, never>>({});

  const [isOpen, setIsOpen] = useState<boolean>(false);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [hasLoadError, setHasLoadError] = useState<boolean>(false);
  const [recentNotifications, setRecentNotifications] = useState<Notification[]>([]);
  const [markingNotificationId, setMarkingNotificationId] = useState<string | null>(null);

  const requestVersion = useRef<number>(0);

  const unreadCount =
    storeUserId === userId && storeUnreadCount !== null
      ? storeUnreadCount
      : (unreadNotificationsCount ?? 0);

  useEffect(() => {
    if (typeof unreadNotificationsCount !== "number") {
      return;
    }

    if (storeUserId !== userId || storeUnreadCount === null) {
      initializeForUser(userId, unreadNotificationsCount);
    }
  }, [initializeForUser, storeUnreadCount, storeUserId, unreadNotificationsCount, userId]);

  const notifications = useMemo<Notification[]>(() => {
    const realtimeNotificationsById = new Map(
      realtimeNotifications.map((notification) => [notification.id, notification]),
    );

    const recentNotificationIds = new Set(
      recentNotifications.map((notification) => notification.id),
    );

    const mergedRecentNotifications = recentNotifications.map((notification) => {
      const realtimeNotification = realtimeNotificationsById.get(notification.id);

      if (realtimeNotification === undefined) {
        return notification;
      }

      return {
        ...notification,
        ...realtimeNotification,
        createdAt: notification.createdAt ?? realtimeNotification.createdAt,
        readAt: realtimeNotification.readAt ?? notification.readAt,
      };
    });

    const newRealtimeNotifications = realtimeNotifications.filter(
      (notification) => !recentNotificationIds.has(notification.id),
    );

    return [...newRealtimeNotifications, ...mergedRecentNotifications].slice(
      0,
      RECENT_NOTIFICATION_LIMIT,
    );
  }, [realtimeNotifications, recentNotifications]);

  const getNotificationPostUrl = useCallback<(notification: Notification) => string | null>(
    (notification) => {
      const { postId, commentId } = notification.target;

      if (postId === null) {
        return null;
      }

      return showPost(postId, {
        query:
          commentId === null
            ? { comments_post: postId }
            : { comments_post: postId, replies_comment: commentId },
      }).url;
    },
    [],
  );

  const loadRecentNotifications = useCallback<() => Promise<void>>(async () => {
    const currentRequestVersion = requestVersion.current + 1;

    requestVersion.current = currentRequestVersion;

    setIsLoading(true);
    setHasLoadError(false);

    try {
      const response = await getRecentNotifications(recent().url);

      if (currentRequestVersion !== requestVersion.current) {
        return;
      }

      setRecentNotifications(response.data);
    } catch {
      if (currentRequestVersion === requestVersion.current) {
        setHasLoadError(true);
      }
    } finally {
      if (currentRequestVersion === requestVersion.current) {
        setIsLoading(false);
      }
    }
  }, [getRecentNotifications]);

  const onOpenChange = useCallback<(open: boolean) => void>(
    (open) => {
      setIsOpen(open);

      if (open) {
        void loadRecentNotifications();
        return;
      }

      requestVersion.current += 1;
      setIsLoading(false);
    },
    [loadRecentNotifications],
  );

  const markNotificationAsRead = useCallback<(notification: Notification) => Promise<boolean>>(
    async (notification) => {
      if (notification.readAt !== null) {
        return true;
      }

      if (markingNotificationId !== null) {
        return false;
      }

      setMarkingNotificationId(notification.id);

      try {
        await postMarkAsRead(markAsRead({ notificationId: notification.id }).url);

        const readAt = new Date().toISOString();

        setRecentNotifications((currentNotifications) =>
          currentNotifications.map((currentNotification) =>
            currentNotification.id === notification.id
              ? { ...currentNotification, readAt }
              : currentNotification,
          ),
        );

        if (storeUserId !== userId) {
          initializeForUser(userId, unreadCount);
        }

        markStoredNotificationAsRead(notification.id, unreadCount);

        toast.success(t("notification/center.toasts.marked_read"));

        return true;
      } catch {
        return false;
      } finally {
        setMarkingNotificationId(null);
      }
    },
    [
      initializeForUser,
      markStoredNotificationAsRead,
      markingNotificationId,
      postMarkAsRead,
      storeUserId,
      t,
      unreadCount,
      userId,
    ],
  );

  const openNotification = useCallback<(notification: Notification) => Promise<void>>(
    async (notification) => {
      const postUrl = getNotificationPostUrl(notification);

      if (postUrl === null) {
        return;
      }

      const wasMarkedAsRead = await markNotificationAsRead(notification);

      if (!wasMarkedAsRead) {
        return;
      }

      setIsOpen(false);
      router.visit(postUrl);
    },
    [getNotificationPostUrl, markNotificationAsRead],
  );

  return {
    hasLoadError,
    isLoading,
    isOpen,
    markingNotificationId,
    notifications,
    unreadCount,
    getNotificationPostUrl,
    markNotificationAsRead,
    onOpenChange,
    openNotification,
  };
}

export default useNotificationDropdown;
